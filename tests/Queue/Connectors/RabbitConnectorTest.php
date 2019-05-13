<?php

namespace Level23\Rabbit\Tests\Queue\Connectors;

use Interop\Amqp\AmqpContext;
use PHPUnit\Framework\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\WorkerStopping;
use Enqueue\AmqpTools\RabbitMqDlxDelayStrategy;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Level23\Rabbit\Queue\RabbitQueue;
use Level23\Rabbit\Queue\Connectors\RabbitConnector;
use Level23\Rabbit\Tests\Mock\AmqpConnectionFactorySpy;
use Level23\Rabbit\Tests\Mock\CustomContextAmqpConnectionFactoryMock;
use Level23\Rabbit\Tests\Mock\DelayStrategyAwareAmqpConnectionFactorySpy;

class RabbitConnectorTest extends TestCase
{
    public function testShouldImplementConnectorInterface()
    {
        $rc = new \ReflectionClass(RabbitConnector::class);

        $this->assertTrue($rc->implementsInterface(ConnectorInterface::class));
    }

    public function testCouldBeConstructedWithDispatcherAsFirstArgument()
    {
        new RabbitConnector($this->createMock(Dispatcher::class));
    }

    public function testThrowsIfFactoryClassIsNotValidClass()
    {
        $connector = new RabbitConnector($this->createMock(Dispatcher::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');
        $connector->connect(['factory' => 'invalidClassName']);
    }

    public function testThrowsIfFactoryClassDoesNotImplementConnectorFactoryInterface()
    {
        $connector = new RabbitConnector($this->createMock(Dispatcher::class));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The factory_class option has to be valid class that implements "Interop\Amqp\AmqpConnectionFactory"');
        $connector->connect(['factory' => \stdClass::class]);
    }

    public function testShouldPassExpectedConfigToConnectionFactory()
    {
        $called = false;
        AmqpConnectionFactorySpy::$spy = function ($config) use (&$called) {
            $called = true;

            $this->assertEquals([
                'dsn' => 'theDsn',
                'host' => 'theHost',
                'port' => 'thePort',
                'user' => 'theLogin',
                'pass' => 'thePassword',
                'vhost' => 'theVhost',
                'ssl_on' => 'theSslOn',
                'ssl_verify' => 'theVerifyPeer',
                'ssl_cacert' => 'theCafile',
                'ssl_cert' => 'theLocalCert',
                'ssl_key' => 'theLocalKey',
                'ssl_passphrase' => 'thePassPhrase',
            ], $config);
        };

        $connector = new RabbitConnector($this->createMock(Dispatcher::class));

        $config = $this->createDummyConfig();
        $config['factory'] = AmqpConnectionFactorySpy::class;

        $connector->connect($config);

        $this->assertTrue($called);
    }

    public function testShouldReturnExpectedInstanceOfQueueOnConnect()
    {
        $connector = new RabbitConnector($this->createMock(Dispatcher::class));

        $config = $this->createDummyConfig();
        $config['factory'] = AmqpConnectionFactorySpy::class;

        $queue = $connector->connect($config);

        $this->assertInstanceOf(RabbitQueue::class, $queue);
    }

    public function testShouldSetRabbitMqDlxDelayStrategyIfConnectionFactoryImplementsDelayStrategyAwareInterface()
    {
        $connector = new RabbitConnector($this->createMock(Dispatcher::class));

        $called = false;
        DelayStrategyAwareAmqpConnectionFactorySpy::$spy = function ($actualStrategy) use (&$called) {
            $this->assertInstanceOf(RabbitMqDlxDelayStrategy::class, $actualStrategy);

            $called = true;
        };

        $config = $this->createDummyConfig();
        $config['factory'] = DelayStrategyAwareAmqpConnectionFactorySpy::class;

        $connector->connect($config);

        $this->assertTrue($called);
    }

    public function testShouldCallContextCloseMethodOnWorkerStoppingEvent()
    {
        $contextMock = $this->createMock(AmqpContext::class);
        $contextMock
            ->expects($this->once())
            ->method('close');

        $dispatcherMock = $this->createMock(Dispatcher::class);
        $dispatcherMock
            ->expects($this->once())
            ->method('listen')
            ->with(WorkerStopping::class, $this->isInstanceOf(\Closure::class))
            ->willReturnCallback(function ($eventName, \Closure $listener) {
                $listener();
            });

        CustomContextAmqpConnectionFactoryMock::$context = $contextMock;

        $connector = new RabbitConnector($dispatcherMock);

        $config = $this->createDummyConfig();
        $config['factory'] = CustomContextAmqpConnectionFactoryMock::class;

        $connector->connect($config);
    }

    /**
     * @return array
     */
    private function createDummyConfig()
    {
        return [
            'dsn' => 'theDsn',
            'host' => 'theHost',
            'port' => 'thePort',
            'user' => 'theLogin',
            'pass' => 'thePassword',
            'vhost' => 'theVhost',
            'queue' => 'aQueueName',
            'exchange' => 'anExchangeName',
            'connection' => [
                'ssl_on'             => 'theSslOn',
                'ssl_verify'         => 'theVerifyPeer',
                'ssl_cacert'         => 'theCafile',
                'ssl_cert'           => 'theLocalCert',
                'ssl_key'            => 'theLocalKey',
                'ssl_passphrase'     => 'thePassPhrase',
            ],
            'options' => [
                'exchange' => [
                    'declare' => false,
                    'type' => \Interop\Amqp\AmqpTopic::TYPE_DIRECT,
                    'passive' => false,
                    'durable' => true,
                    'auto_delete' => false,
                ],

                'queue' => [
                    'declare' => false,
                    'bind' => false,
                    'passive' => false,
                    'durable' => true,
                    'exclusive' => false,
                    'auto_delete' => false,
                    'arguments' => '[]',
                ],
            ],
        ];
    }
}
