<?php

namespace Level23\Rabbit\Queue;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;

class RabbitDeclaration
{
    /**
     * @var \Interop\Amqp\AmqpTopic
     */
    protected $topic;

    /**
     * @var \Interop\Amqp\AmqpQueue
     */
    protected $queue;

    public function __construct(AmqpTopic $topic, AmqpQueue $queue)
    {
        $this->topic = $topic;
        $this->queue = $queue;
    }

    /**
     * @return \Interop\Amqp\AmqpTopic
     */
    public function getTopic(): AmqpTopic
    {
        return $this->topic;
    }

    /**
     * @return \Interop\Amqp\AmqpQueue
     */
    public function getQueue(): AmqpQueue
    {
        return $this->queue;
    }
}