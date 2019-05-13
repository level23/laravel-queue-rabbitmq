<?php

namespace Level23\Rabbit\Tests\Functional;

use Enqueue\AmqpLib\AmqpContext;
use Illuminate\Events\Dispatcher;
use PhpAmqpLib\Connection\AMQPSSLConnection;
use Level23\Rabbit\Queue\RabbitQueue;
use Level23\Rabbit\Queue\Connectors\RabbitConnector;

/**
 * @group functional
 */
class SslConnectionTest extends FunctionalTestCase
{
    public function testConnectorEstablishSecureConnectionWithRabbitMQBroker()
    {
        $config = $this->getDefaultConfig([
            'port'       => getenv('PORT_SSL'),
            'connection' => [
                'ssl_on'     => true,
                'ssl_verify' => false,
                'ssl_cacert' => getenv('RABBITMQ_SSL_CACERT'),
            ],
        ]);

        $connector = new RabbitConnector(new Dispatcher());
        /** @var RabbitQueue $queue */
        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitQueue::class, $queue);

        /** @var AmqpContext $context */
        $context = $queue->getContext();
        $this->assertInstanceOf(AmqpContext::class, $context);

        $this->assertInstanceOf(AMQPSSLConnection::class, $context->getLibChannel()->getConnection());
        $this->assertTrue($context->getLibChannel()->getConnection()->isConnected());
    }
}
