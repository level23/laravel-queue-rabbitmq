<?php

namespace Level23\Rabbit\Tests\Mock;

use Interop\Queue\Context;

class CustomContextAmqpConnectionFactoryMock implements \Interop\Amqp\AmqpConnectionFactory
{
    public static $context;

    public function createContext(): Context
    {
        return static::$context;
    }
}
