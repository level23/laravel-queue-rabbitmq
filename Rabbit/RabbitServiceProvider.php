<?php

namespace Level23\Rabbit;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Level23\Rabbit\Queue\Connectors\RabbitConnector;

class RabbitServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/rabbitmq.php', 'queue.connections.rabbitmq'
        );
    }

    /**
     * Register the application's event listeners.
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var QueueManager $queue */
        $queue = $this->app['queue'];

        $queue->addConnector('rabbitmq', function () {
            return new RabbitConnector($this->app['events']);
        });
    }
}
