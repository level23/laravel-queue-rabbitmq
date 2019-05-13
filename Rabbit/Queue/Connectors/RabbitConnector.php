<?php

namespace Level23\Rabbit\Queue\Connectors;

use Illuminate\Support\Arr;
use Illuminate\Contracts\Queue\Queue;
use Enqueue\AmqpTools\DelayStrategyAware;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Queue\Connectors\ConnectorInterface;
use LogicException;
use Level23\Rabbit\Queue\RabbitQueue;
use Interop\Amqp\AmqpConnectionFactory as InteropAmqpConnectionFactory;
use Enqueue\AmqpLib\AmqpConnectionFactory as EnqueueAmqpConnectionFactory;

class RabbitConnector implements ConnectorInterface
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    public function __construct(Dispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Establish a queue connection.
     *
     * @param array $config
     *
     * @return Queue
     */
    public function connect(array $config): Queue
    {
        $context = $this->amqpContext($config);

        $this->dispatcher->listen(WorkerStopping::class, function () use ($context) {
            $context->close();
        });

        return new RabbitQueue($context, $config);
    }

    /**
     * @param array $config
     *
     * @return \Enqueue\AmqpLib\AmqpContext|\Interop\Queue\Context
     */
    protected function amqpContext(array $config)
    {
        $factoryClass = $config['factory'] ?? EnqueueAmqpConnectionFactory::class;

        if(!class_exists($factoryClass) || !is_a($factoryClass, InteropAmqpConnectionFactory::class, true)) {
            throw new LogicException(sprintf(
                'The factory_class option has to be valid class that implements "%s"',
                InteropAmqpConnectionFactory::class
            ));
        }

        $factoryConfig = array_merge(
            Arr::only($config, ['dsn', 'host', 'port', 'user', 'pass', 'vhost']),
            Arr::get($config,'connection',[])
        );

        /** @var \Enqueue\AmqpLib\AmqpConnectionFactory $factory */
        $factory = new $factoryClass($factoryConfig);

        if ($factory instanceof DelayStrategyAware) {
            $factory->setDelayStrategy(new RabbitMqDlxDelayStrategy());
        }

        return $factory->createContext();
    }
}
