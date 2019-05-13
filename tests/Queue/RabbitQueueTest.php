<?php

namespace Level23\Rabbit\Queue;

use Interop\Amqp\AmqpQueue;
use Interop\Amqp\AmqpTopic;
use Psr\Log\LoggerInterface;
use Interop\Amqp\AmqpContext;
use Interop\Amqp\AmqpMessage;
use Interop\Amqp\AmqpConsumer;
use Interop\Amqp\AmqpProducer;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Level23\Rabbit\Queue\Jobs\RabbitJob;

class RabbitQueueTest extends TestCase
{
    public function testShouldImplementQueueInterface()
    {
        $rc = new \ReflectionClass(RabbitQueue::class);

        $this->assertTrue($rc->implementsInterface(\Illuminate\Contracts\Queue\Queue::class));
    }

    public function testShouldBeSubClassOfQueue()
    {
        $rc = new \ReflectionClass(RabbitQueue::class);

        $this->assertTrue($rc->isSubclassOf(\Illuminate\Queue\Queue::class));
    }

    public function testCouldBeConstructedWithExpectedArguments()
    {
        new RabbitQueue($this->createAmqpContext(), $this->createDummyConfig());
    }

    public function testShouldGenerateNewCorrelationIdIfNotSet()
    {
        $queue = new RabbitQueue($this->createAmqpContext(), $this->createDummyConfig());

        $firstId = $queue->getCorrelationId();
        $secondId = $queue->getCorrelationId();

        $this->assertNotEmpty($firstId);
        $this->assertNotEmpty($secondId);
        $this->assertNotSame($firstId, $secondId);
    }

    public function testShouldReturnPreviouslySetCorrelationId()
    {
        $expectedId = 'theCorrelationId';

        $queue = new RabbitQueue($this->createAmqpContext(), $this->createDummyConfig());

        $queue->setCorrelationId($expectedId);

        $this->assertSame($expectedId, $queue->getCorrelationId());
        $this->assertSame($expectedId, $queue->getCorrelationId());
    }

    public function testShouldAllowGetContextSetInConstructor()
    {
        $context = $this->createAmqpContext();

        $queue = new RabbitQueue($context, $this->createDummyConfig());

        $this->assertSame($context, $queue->getContext());
    }

    public function testShouldReturnExpectedNumberOfMessages()
    {
        $expectedQueueName = 'theQueueName';
        $queue = $this->createMock(AmqpQueue::class);
        $expectedCount = 123321;

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('declareQueue')
            ->with($this->identicalTo($queue))
            ->willReturn($expectedCount);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertSame($expectedCount, $queue->size($expectedQueueName));
    }

    public function testShouldSendExpectedMessageOnPushRaw()
    {
        $expectedQueueName = 'theQueueName';
        $expectedBody = 'thePayload';
        $topic = $this->createMock(AmqpTopic::class);

        $queue = $this->createMock(AmqpQueue::class);
        $queue->expects($this->any())->method('getQueueName')->willReturn('theQueueName');

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedQueueName, $expectedBody, $topic) {
                $this->assertSame($topic, $actualTopic);
                $this->assertSame($expectedBody, $message->getBody());
                $this->assertSame($expectedQueueName, $message->getRoutingKey());
                $this->assertSame('application/json', $message->getContentType());
                $this->assertSame(AmqpMessage::DELIVERY_MODE_PERSISTENT, $message->getDeliveryMode());
                $this->assertNotEmpty($message->getCorrelationId());
                $this->assertNull($message->getProperty(RabbitJob::ATTEMPT_COUNT_HEADERS_KEY));
            });
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay');

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with($expectedBody)
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage($expectedBody));

        $context
            ->expects($this->once())
            ->method('createQueue')
            ->with($expectedQueueName)
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', $expectedQueueName);
    }

    public function testShouldSetAttemptCountPropIfNotNull()
    {
        $expectedAttempts = 54321;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send')
            ->with($this->identicalTo($topic), $this->isInstanceOf(AmqpMessage::class))
            ->willReturnCallback(function ($actualTopic, AmqpMessage $message) use ($expectedAttempts) {
                $this->assertSame($expectedAttempts, $message->getProperty(RabbitJob::ATTEMPT_COUNT_HEADERS_KEY));
            });
        $producer
            ->expects($this->never())
            ->method('setDeliveryDelay');

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage());
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class));
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['attempts' => $expectedAttempts]);
    }

    public function testShouldSetDeliveryDelayIfDelayOptionPresent()
    {
        $expectedDelay = 56;
        $expectedDeliveryDelay = 56000;

        $topic = $this->createMock(AmqpTopic::class);

        $producer = $this->createMock(AmqpProducer::class);
        $producer
            ->expects($this->once())
            ->method('send');
        $producer
            ->expects($this->once())
            ->method('setDeliveryDelay')
            ->with($expectedDeliveryDelay);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($topic);
        $context
            ->expects($this->once())
            ->method('createMessage')
            ->with()
            ->willReturn(new \Interop\Amqp\Impl\AmqpMessage());
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($this->createMock(AmqpQueue::class));
        $context
            ->expects($this->once())
            ->method('createProducer')
            ->willReturn($producer);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $queue->pushRaw('thePayload', 'aQueue', ['delay' => $expectedDelay]);
    }

    public function testShouldReturnNullIfNoMessagesOnQueue()
    {
        $queue = $this->createMock(AmqpQueue::class);

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn(null);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $this->assertNull($queue->pop('aQueue'));
    }

    public function testShouldReturnRabbitMQJobIfMessageReceivedFromQueue()
    {
        $queue = $this->createMock(AmqpQueue::class);

        $message = new \Interop\Amqp\Impl\AmqpMessage('thePayload');

        $consumer = $this->createMock(AmqpConsumer::class);
        $consumer
            ->expects($this->once())
            ->method('receiveNoWait')
            ->willReturn($message);
        $consumer
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $context = $this->createAmqpContext();
        $context
            ->expects($this->once())
            ->method('createTopic')
            ->willReturn($this->createMock(AmqpTopic::class));
        $context
            ->expects($this->once())
            ->method('createQueue')
            ->willReturn($queue);
        $context
            ->expects($this->once())
            ->method('createConsumer')
            ->with($this->identicalTo($queue))
            ->willReturn($consumer);

        $queue = new RabbitQueue($context, $this->createDummyConfig());
        $queue->setContainer($this->createDummyContainer());

        $job = $queue->pop('aQueue');

        $this->assertInstanceOf(RabbitJob::class, $job);
    }

    /**
     * @return AmqpContext|\PHPUnit_Framework_MockObject_MockObject|AmqpContext
     */
    private function createAmqpContext()
    {
        return $this->createMock(AmqpContext::class);
    }

    private function createDummyContainer()
    {
        $logger = $this->createMock(LoggerInterface::class);

        $container = new Container();
        $container['log'] = $logger;

        return $container;
    }

    /**
     * @return array
     */
    private function createDummyConfig()
    {
        return [
            'dsn' => 'aDsn',
            'host' => 'aHost',
            'port' => 'aPort',
            'user' => 'aLogin',
            'pass' => 'aPassword',
            'vhost' => 'aVhost',
            'queue' => 'aQueueName',
            'exchange' => 'anExchangeName',
            'connection' => [
                'ssl_on'         => 'aSslOn',
                'ssl_verify'     => 'aVerifyPeer',
                'ssl_cacert'     => 'aCafile',
                'ssl_cert'       => 'aLocalCert',
                'ssl_key'        => 'aLocalKey',
                'ssl_passphrase' => 'aLocalPassphrase',
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
            ]
        ];
    }
}
