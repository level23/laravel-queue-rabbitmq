<?php

/**
 * This is an example of queue connection configuration.
 * It will be merged into config/queue.php.
 * You need to set proper values in `.env`.
 */
return [

    'driver'   => 'rabbitmq',

    /**
     * The default queue used
     */
    'queue'    => env('RABBITMQ_QUEUE', 'default'),

    /**
     * The default exchange used
     */
    'exchange' => env('RABBITMQ_EXCHANGE', 'amq.direct'),

    /*
     * Could be one a class that implements \Interop\Amqp\AmqpConnectionFactory for example:
     *  - \EnqueueAmqpExt\AmqpConnectionFactory if you install enqueue/amqp-ext
     *  - \EnqueueAmqpLib\AmqpConnectionFactory if you install enqueue/amqp-lib
     *  - \EnqueueAmqpBunny\AmqpConnectionFactory if you install enqueue/amqp-bunny
     */
    'factory'  => Enqueue\AmqpLib\AmqpConnectionFactory::class,

    'dsn'        => env('RABBITMQ_DSN', null),
    'host'       => env('RABBITMQ_HOST', '127.0.0.1'),
    'port'       => env('RABBITMQ_PORT', 5672),
    'vhost'      => env('RABBITMQ_VHOST', '/'),
    'user'       => env('RABBITMQ_LOGIN', 'guest'),
    'pass'       => env('RABBITMQ_PASSWORD', 'guest'),
    'connection' => [
        'read_timeout'       => env('RABBITMQ_READ_TIMEOUT', 3),
        'write_timeout'      => env('RABBITMQ_WRITE_TIMEOUT', 3),
        'connection_timeout' => env('RABBITMQ_CONNECTION_TIMEOUT', 3),
        'heartbeat'          => env('RABBITMQ_HEARTBEAT', 0),
        'persisted'          => env('RABBITMQ_PERSISTED', false),
        'lazy'               => env('RABBITMQ_LAZY', true),
        'ssl_on'             => env('RABBITMQ_SSL_ON', false),
        'ssl_verify'         => env('RABBITMQ_SSL_VERIFY', true),
        'ssl_cacert'         => env('RABBITMQ_SSL_CACERT', null),
        'ssl_cert'           => env('RABBITMQ_SSL_CERT', null),
        'ssl_key'            => env('RABBITMQ_SSL_KEY', null),
        'ssl_passphrase'     => env('RABBITMQ_SSL_PASSPHRASE', null),
    ],

    'options' => [

        'exchange' => [

            /*
            * Determine if exchange should be created if it does not exist.
            */
            'declare'     => env('RABBITMQ_EXCHANGE_DECLARE', true),

            /*
            * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
            */
            'type'        => env('RABBITMQ_EXCHANGE_TYPE', \Interop\Amqp\AmqpTopic::TYPE_DIRECT),
            'passive'     => env('RABBITMQ_EXCHANGE_PASSIVE', false),
            'durable'     => env('RABBITMQ_EXCHANGE_DURABLE', true),
            'auto_delete' => env('RABBITMQ_EXCHANGE_AUTODELETE', false),
            'arguments'   => env('RABBITMQ_EXCHANGE_ARGUMENTS'),
        ],

        'queue' => [

            /*
            * Determine if queue should be created if it does not exist.
            */
            'declare'     => env('RABBITMQ_QUEUE_DECLARE', true),

            /*
            * Determine if queue should be binded to the exchange created.
            */
            'bind'        => env('RABBITMQ_QUEUE_DECLARE_BIND', true),

            /*
            * Read more about possible values at https://www.rabbitmq.com/tutorials/amqp-concepts.html
            */
            'passive'     => env('RABBITMQ_QUEUE_PASSIVE', false),
            'durable'     => env('RABBITMQ_QUEUE_DURABLE', true),
            'exclusive'   => env('RABBITMQ_QUEUE_EXCLUSIVE', false),
            'auto_delete' => env('RABBITMQ_QUEUE_AUTODELETE', false),
            'arguments'   => env('RABBITMQ_QUEUE_ARGUMENTS'),
        ],
    ],
];
