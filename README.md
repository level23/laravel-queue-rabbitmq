RabbitMQ Queue driver for Laravel
======================
[![Build Status](https://img.shields.io/travis/level23/rabbit.svg?style=flat-square)](https://travis-ci.org/level23/rabbit)
[![Total Downloads](https://poser.pugx.org/level23/rabbit/downloads?format=flat-square)](https://packagist.org/packages/level23/rabbit)
[![License](https://poser.pugx.org/level23/rabbit/license?format=flat-square)](https://packagist.org/packages/level23/rabbit)

## Fork

This is a fork from https://github.com/vyuldashev/laravel-queue-rabbitmq. We needed a similar implementation but with some custom features.

## Installation

You can install this package via composer using this command:

```
composer require level23/rabbit
```

The package will automatically register itself using Laravel auto-discovery.

Setup connection in `config/queue.php`

```php
'connections' => [
    // ...
    'rabbitmq' => [
        
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
    ],
    // ...    
],
```

## Laravel Usage

Once you completed the configuration you can use Laravel Queue API. If you used other queue drivers you do not need to change anything else. If you do not know how to use Queue API, please refer to the official Laravel documentation: http://laravel.com/docs/queues

## Lumen Usage

For Lumen usage the service provider should be registered manually as follow in `bootstrap/app.php`:

```php
$app->register(Level23\Rabbit\RabbitServiceProvider::class);
```


## Using other AMQP transports

The package uses [enqueue/amqp-lib](https://github.com/php-enqueue/enqueue-dev/blob/master/docs/transport/amqp_lib.md) transport which is based on [php-amqplib](https://github.com/php-amqplib/php-amqplib). 
There is possibility to use any [amqp interop](https://github.com/queue-interop/queue-interop#amqp-interop) compatible transport, for example `enqueue/amqp-ext` or `enqueue/amqp-bunny`.
Here's an example on how one can change the transport to `enqueue/amqp-bunny`.

First, install desired transport package:

```bash
composer require enqueue/amqp-bunny:^0.8
```
  
Change the factory class in `config/queue.php`:

```php
    // ...
    'connections' => [
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'factory' => Enqueue\AmqpBunny\AmqpConnectionFactory::class,
        ],
    ],
```

## Testing

Setup RabbitMQ using `docker-compose`:
```bash
docker-compose up -d
```

Run tests:

``` bash
composer test
```

## Contribution

You can contribute to this package by discovering bugs and opening issues. Please, add to which version of package you create pull request or issue. (e.g. [5.2] Fatal error on delayed job)
