<?php

namespace Level23\Rabbit\Tests\Functional;

use Psr\Log\NullLogger;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Level23\Rabbit\Queue\RabbitQueue;
use Level23\Rabbit\Queue\Jobs\RabbitJob;
use Level23\Rabbit\Queue\Connectors\RabbitConnector;

/**
 * @group functional
 */
class SendAndReceiveDelayedMessageTest extends FunctionalTestCase
{
    public function test()
    {
        $config = $this->getDefaultConfig();

        $connector = new RabbitConnector(new Dispatcher());
        /** @var RabbitQueue $queue */
        $queue = $connector->connect($config);
        $queue->setContainer($this->createDummyContainer());

        // we need it to declare exchange\queue on RabbitMQ side.
        $queue->pushRaw('something');

        $queue->getContext()->purgeQueue($queue->getContext()->createQueue('default'));

        $expectedPayload = __METHOD__.microtime(true);

        $queue->pushRaw($expectedPayload, null, ['delay' => 3]);

        sleep(1);

        $this->assertNull($queue->pop());

        sleep(4);

        $job = $queue->pop();

        $this->assertInstanceOf(RabbitJob::class, $job);
        $this->assertSame($expectedPayload, $job->getRawBody());

        $job->delete();
    }

}
