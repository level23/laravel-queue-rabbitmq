<?php

namespace Level23\Rabbit\Queue;

use Illuminate\Queue\Queue;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\Impl\AmqpBind;
use Level23\Rabbit\Queue\Jobs\RabbitJob;
use Illuminate\Contracts\Queue\Queue as QueueContract;

class RabbitQueue extends Queue implements QueueContract
{
    /**
     * The name of the default queue.
     *
     * @var string
     */
    protected $queue;

    /**
     * The name of the default exchange.
     *
     * @var string
     */
    protected $exchange;

    /**
     * @var array
     */
    protected $queueOptions = [];

    /**
     * @var array
     */
    protected $exchangeOptions = [];

    /**
     * @var array
     */
    protected $declaredExchanges = [];

    /**
     * @var array
     */
    protected $declaredQueues = [];

    /**
     * @var AmqpContext
     */
    protected $context;

    protected $correlationId;

    public function __construct(AmqpContext $context, array $config)
    {
        $this->context  = $context;
        $this->queue    = $config['queue'] ?? 'default';
        $this->exchange = $config['exchange'] ?? null;

        $this->setQueueOptions($config['options']['queue'] ?? []);
        $this->setExchangeOptions($config['options']['exchange'] ?? []);
    }

    /**
     * Get the size of the queue.
     *
     * @param string $queue
     *
     * @return int
     */
    public function size($queue = null): int
    {
        $declaration = $this->createDeclaration($queue);

        return $this->context->declareQueue($declaration->getQueue());
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string|object $job
     * @param mixed         $data
     * @param string        $queue
     *
     * @return mixed
     * @throws \Interop\Queue\Exception
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, $this->parseJobOptions($job));
    }

    /**
     * Push a raw payload onto the queue.
     *
     * @param string $payload
     * @param string $queue
     * @param array  $options
     *
     * @return mixed
     * @throws \Interop\Queue\Exception
     */
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        $declaration = $this->createDeclaration($queue, $options);

        $message = $this->context->createMessage($payload);
        $message->setRoutingKey($declaration->getQueue()->getQueueName());
        $message->setCorrelationId($this->getCorrelationId());
        $message->setContentType('application/json');
        $message->setDeliveryMode(AmqpMessage::DELIVERY_MODE_PERSISTENT);

        if (isset($options['headers'])) {
            $message->setHeaders($options['headers']);
        }

        if (isset($options['properties'])) {
            $message->setProperties($options['properties']);
        }

        if (isset($options['attempts'])) {
            $message->setProperty(RabbitJob::ATTEMPT_COUNT_HEADERS_KEY, $options['attempts']);
        }

        $producer = $this->context->createProducer();
        if (isset($options['delay']) && $options['delay'] > 0) {
            $producer->setDeliveryDelay($options['delay'] * 1000);
        }

        $producer->send($declaration->getTopic(), $message);

        return $message->getCorrelationId();
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string|object                        $job
     * @param mixed                                $data
     * @param string                               $queue
     *
     * @return mixed
     * @throws \Interop\Queue\Exception
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->pushRaw(
            $this->createPayload($job, $queue, $data),
            $queue,
            $this->parseJobOptions($job, ['delay' => $this->secondsUntil($delay)])
        );
    }

    /**
     * Release a reserved job back onto the queue.
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string|object                        $job
     * @param mixed                                $data
     * @param string                               $queue
     * @param int                                  $attempts
     *
     * @return mixed
     * @throws \Interop\Queue\Exception
     */
    public function release($delay, $job, $data, $queue, $attempts = 0)
    {
        return $this->pushRaw($this->createPayload($job, $queue, $data), $queue, $this->parseJobOptions($job,[
            'delay'    => $this->secondsUntil($delay),
            'attempts' => $attempts,
        ]));
    }

    /**
     * Pop the next job off of the queue.
     *
     * @param null $queue
     *
     * @return \Illuminate\Contracts\Queue\Job|null|void
     */
    public function pop($queue = null)
    {
        $declaration = $this->createDeclaration($queue);

        $consumer = $this->context->createConsumer($declaration->getQueue());

        if ($message = $consumer->receiveNoWait()) {
            return new RabbitJob($this->container, $this, $consumer, $message);
        }
    }

    /**
     * Retrieves the correlation id, or a unique id.
     *
     * @return string
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId ?: uniqid('', true);
    }

    /**
     * Sets the correlation id for a message to be published.
     *
     * @param string $id
     *
     * @return void
     */
    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    /**
     * @return AmqpContext
     */
    public function getContext(): AmqpContext
    {
        return $this->context;
    }

    /**
     * @param string|null $queue
     * @param array       $options
     *
     * @return \Level23\Rabbit\Queue\RabbitDeclaration
     */
    protected function createDeclaration(string $queue = null, array $options = []): RabbitDeclaration
    {
        // Get the queue name
        $queue = $this->getQueue($queue);

        // Parse the queue options
        $queueOptions = $this->getQueueOptions($options);

        // Create the queue object
        $amqpQueue = $this->createQueue($queue, $queueOptions);

        $exchangeOptions = $this->getExchangeOptions($options);

        $exchange = $this->getExchange($queue, $options);

        // Create the exchange
        $amqpTopic = $this->createExchange(
            $exchange,
            $exchangeOptions
        );

        // If bind is defined we bind the exchange to the queue
        if ($queueOptions['bind']) {
            $this->context->bind(new AmqpBind($amqpQueue, $amqpTopic, $amqpQueue->getQueueName()));
        }

        return new RabbitDeclaration($amqpTopic, $amqpQueue);
    }

    /**
     * @param       $name
     * @param array $options
     *
     * @return AmqpTopic
     */
    protected function createExchange($name, array $options = [])
    {
        $topic = $this->context->createTopic($name);
        $topic->setType($options['type']);
        $topic->setArguments($options['arguments']);

        if ($options['passive']) {
            $topic->addFlag(AmqpTopic::FLAG_PASSIVE);
        }

        if ($options['durable']) {
            $topic->addFlag(AmqpTopic::FLAG_DURABLE);
        }

        if ($options['auto_delete']) {
            $topic->addFlag(AmqpTopic::FLAG_AUTODELETE);
        }

        if ($options['declare'] && !in_array($name, $this->declaredExchanges, true)) {
            $this->context->declareTopic($topic);

            $this->declaredExchanges[] = $name;
        }

        return $topic;
    }

    /**
     * @param       $name
     * @param array $options
     *
     * @return \Interop\Amqp\AmqpQueue
     */
    public function createQueue($name, array $options = [])
    {
        $queue = $this->context->createQueue($name);
        $queue->setArguments($options['arguments']);

        if ($options['passive']) {
            $queue->addFlag(AmqpQueue::FLAG_PASSIVE);
        }

        if ($options['durable']) {
            $queue->addFlag(AmqpQueue::FLAG_DURABLE);
        }

        if ($options['exclusive']) {
            $queue->addFlag(AmqpQueue::FLAG_EXCLUSIVE);
        }

        if ($options['auto_delete']) {
            $queue->addFlag(AmqpQueue::FLAG_AUTODELETE);
        }

        if ($options['declare'] && !in_array($name, $this->declaredQueues, true)) {
            $this->context->declareQueue($queue);

            $this->declaredQueues[] = $name;
        }

        return $queue;
    }

    /**
     * @param null $queue
     *
     * @return string
     */
    protected function getQueue($queue = null)
    {
        return $queue ?: $this->queue;
    }

    /**
     * @param null  $queue
     *
     * @param array $options
     *
     * @return string
     */
    protected function getExchange($queue = null, array $options = [])
    {
        $name = $options['exchange'] ?? null;

        if(is_string($name)) {
            return $name;
        }

        if(is_array($name)) {
            $name = $name['name'] ?? null;
        }

        return $name ?: ($this->exchange ?: $queue);
    }

    /**
     * @param mixed  $job
     * @param string $queue
     * @param string $data
     *
     * @return array
     */
    protected function createPayloadArray($job, $queue, $data = '')
    {
        return array_merge(parent::createPayloadArray($job, $queue, $data), [
            'id' => $this->getRandomId(),
        ]);
    }

    /**
     * Get a random ID string.
     *
     * @return string
     */
    protected function getRandomId(): string
    {
        return Str::random(32);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getExchangeOptions(array $options = [])
    {
        return array_merge($this->exchangeOptions, $options['exchange'] ?? []);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function getQueueOptions(array $options = [])
    {
        return array_merge($this->queueOptions, $options['queue'] ?? []);
    }

    /**
     * @param array $options
     *
     * @return \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitQueue
     */
    public function setQueueOptions(array $options = [])
    {
        $this->queueOptions = $this->parseArguments($options);

        return $this;
    }

    /**
     * @param array $options
     *
     * @return \VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitQueue
     */
    public function setExchangeOptions(array $options = [])
    {
        $this->exchangeOptions = $this->parseArguments($options);

        return $this;
    }

    /**
     * @param array $options
     *
     * @return array
     */
    protected function parseArguments(array $options)
    {
        $options['arguments'] = $options['arguments'] ?? [];

        if (is_string($options['arguments'])) {
            $options['arguments'] = json_decode($options['arguments'], true);
        }

        return $options;
    }

    /**
     * @param       $job
     *
     * @param array $options
     *
     * @return array
     */
    protected function parseJobOptions($job, array $options = [])
    {
        if(!is_object($job)) {
            return $options;
        }

        $exchange = $job->exchange ?? null;

        if($exchange){
            $options['exchange'] = $exchange;
        }

        return $options;
    }
}
