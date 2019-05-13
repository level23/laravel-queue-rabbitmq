<?php

namespace Level23\Rabbit\Queue\Jobs;

use Exception;
use Illuminate\Support\Str;
use Interop\Amqp\AmqpMessage;
use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Level23\Rabbit\Queue\RabbitQueue;

class RabbitJob extends Job implements JobContract
{
    /**
     * Same as RabbitMQQueue, used for attempt counts.
     */
    public const ATTEMPT_COUNT_HEADERS_KEY = 'attempts_count';

    protected $connection;
    protected $consumer;

    /**
     * @var \Interop\Amqp\AmqpMessage
     */
    protected $message;

    public function __construct(
        Container $container,
        RabbitQueue $connection,
        AmqpConsumer $consumer,
        AmqpMessage $message
    ) {
        $this->container = $container;
        $this->connection = $connection;
        $this->consumer = $consumer;
        $this->message = $message;
        $this->queue = $consumer->getQueue()->getQueueName();
        $this->connectionName = $connection->getConnectionName();
    }

    /**
     * Get the number of times the job has been attempted.
     *
     * @return int
     */
    public function attempts(): int
    {
        // set default job attempts to 1 so that jobs can run without retry
        return $this->message->getProperty(self::ATTEMPT_COUNT_HEADERS_KEY, 1);
    }

    /**
     * Get the raw body string for the job.
     *
     * @return string
     */
    public function getRawBody(): string
    {
        return $this->message->getBody();
    }

    /**
     * Delete the job from the queue.
     *
     * @return void
     */
    public function delete(): void
    {
        parent::delete();

        $this->consumer->acknowledge($this->message);
    }

    /**
     * Release the job back into the queue.
     *
     * Accepts a delay specified in seconds.
     *
     * @param  int   $delay
     * @return void
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        // Acknowledge the message
        $this->consumer->acknowledge($this->message);

        $this->message->setProperty(static::ATTEMPT_COUNT_HEADERS_KEY, $this->attempts() + 1);

        var_dump($this->message);
        die();

        $body = $this->payload();

        /*
         * Some jobs don't have the command set, so fall back to just sending it the job name string
         */
        if (isset($body['data']['command']) === true) {
            $job = $this->unserialize($body);
        } else {
            $job = $this->getName();
        }

        $data = $body['data'];

//        $this->connection->pushRaw($)

        $this->connection->release($delay, $job, $data, $this->getQueue(), $this->attempts() + 1);
    }

    /**
     * Get the job identifier.
     *
     * @return string
     * @throws \Interop\Queue\Exception
     */
    public function getJobId(): string
    {
        return $this->message->getCorrelationId();
    }

    /**
     * Sets the job identifier.
     *
     * @param string $id
     *
     * @return void
     */
    public function setJobId($id): void
    {
        $this->connection->setCorrelationId($id);
    }

    /**
     * Unserialize job.
     *
     * @param array $body
     *
     * @throws Exception
     *
     * @return mixed
     */
    protected function unserialize(array $body)
    {
        try {
            /* @noinspection UnserializeExploitsInspection */
            return unserialize($body['data']['command']);
        } catch (Exception $exception) {
            if (
                $this->causedByDeadlock($exception) ||
                Str::contains($exception->getMessage(), ['detected deadlock'])
            ) {
                sleep(2);

                return $this->unserialize($body);
            }

            throw $exception;
        }
    }
}
