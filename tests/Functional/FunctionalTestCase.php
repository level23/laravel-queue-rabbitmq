<?php

namespace Level23\Rabbit\Tests\Functional;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;
use Enqueue\AmqpLib\AmqpConnectionFactory;
use Psr\Log\NullLogger;

abstract class FunctionalTestCase extends TestCase
{
    protected function getDefaultConfig(array $config = [])
    {
        return array_merge(
            [
                'factory' => AmqpConnectionFactory::class,
                'dsn'           => null,
                'host'          => getenv('HOST'),
                'port'          => getenv('PORT'),
                'user'         => 'guest',
                'pass'      => 'guest',
                'vhost'         => '/',
                'queue'         => 'default',
                'exchange'      => null,
            ],
            $config,
            [
                'connection' => array_merge([
                    'ssl_on'         => false,
                    'ssl_verify'     => true,
                    'ssl_cacert'     => null,
                    'ssl_cert'       => null,
                    'ssl_key'        => null,
                    'ssl_passphrase' => null,
                ], $config['connection'] ?? []),
                'options'    => [
                    'exchange' => array_merge([
                        'declare'     => true,
                        'type'        => \Interop\Amqp\AmqpTopic::TYPE_DIRECT,
                        'passive'     => false,
                        'durable'     => true,
                        'auto_delete' => false,
                    ], $config['options']['exchange'] ?? []),
                    'queue'    => array_merge([
                        'declare'     => true,
                        'bind'        => true,
                        'passive'     => false,
                        'durable'     => true,
                        'exclusive'   => false,
                        'auto_delete' => false,
                        'arguments'   => '[]',
                    ], $config['options']['queue'] ?? []),
                ],
            ]);
    }

    protected function createDummyContainer()
    {
        $container = new Container();
        $container['log'] = new NullLogger();

        return $container;
    }
}