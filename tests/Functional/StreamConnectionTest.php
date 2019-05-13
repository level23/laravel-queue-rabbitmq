<?php

namespace Level23\Rabbit\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Enqueue\AmqpLib\AmqpContext;
use Illuminate\Events\Dispatcher;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Level23\Rabbit\Queue\RabbitQueue;
use Level23\Rabbit\Queue\Connectors\RabbitConnector;

/**
 * @group functional
 */
class StreamConnectionTest extends FunctionalTestCase
{
    public function testConnectorEstablishSecureConnectionWithRabbitMQBroker()
    {
        $config = $this->getDefaultConfig();

        $connector = new RabbitConnector(new Dispatcher());
        /** @var RabbitQueue $queue */
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitQueue::class, $queue);

        /** @var AmqpContext $context */
        $context = $queue->getContext();
        $this->assertInstanceOf(AmqpContext::class, $context);

        $this->assertInstanceOf(AMQPStreamConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
