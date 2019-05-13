<?php

namespace Level23\Rabbit\Tests\Queue\Jobs;

use Illuminate\Queue\Jobs\Job;
use Interop\Amqp\AmqpConsumer;
use PHPUnit\Framework\TestCase;
use Interop\Amqp\Impl\AmqpMessage;
use Illuminate\Container\Container;
use Illuminate\Database\DetectsDeadlocks;
use Illuminate\Contracts\Queue\Job as JobContract;
use Level23\Rabbit\Queue\RabbitQueue;
use Level23\Rabbit\Queue\Jobs\RabbitJob;

class RabbitJobTest extends TestCase
{
    public function testShouldImplementQueueInterface()
    {
        $rc = new \ReflectionClass(RabbitJob::class);

        $this->assertTrue($rc->implementsInterface(JobContract::class));
    }

    public function testShouldBeSubClassOfQueue()
    {
        $rc = new \ReflectionClass(RabbitJob::class);

        $this->assertTrue($rc->isSubclassOf(Job::class));
    }

    public function testCouldBeConstructedWithExpectedArguments()
    {
        $queue = $this->createMock(\Interop\Amqp\AmqpQueue::class);
        $queue
            ->expects($this->once())
            ->method('getQueueName')
            ->willReturn('theQueueName');

        $consumerMock = $this->createConsumerMock();
        $consumerMock
            ->expects($this->once())
            ->method('getQueue')
            ->willReturn($queue);

        $connectionMock = $this->createRabbitMQQueueMock();
        $connectionMock
            ->expects($this->any())
            ->method('getConnectionName')
            ->willReturn('theConnectionName');

        $job = new RabbitJob(
            new Container(),
            $connectionMock,
            $consumerMock,
            new AmqpMessage()
        );

        $this->assertAttributeSame('theQueueName', 'queue', $job);
        $this->assertSame('theConnectionName', $job->getConnectionName());
    }

    /**
     * @return AmqpConsumer|\PHPUnit_Framework_MockObject_MockObject|AmqpConsumer
     */
    private function createConsumerMock()
    {
        return $this->createMock(AmqpConsumer::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|RabbitQueue|RabbitQueue
     */
    private function createRabbitMQQueueMock()
    {
        return $this->createMock(RabbitQueue::class);
    }
}
