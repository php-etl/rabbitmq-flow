<?php

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Client;

class ClientMiddleware
{
    private static ?Client $instance = null;

    public static function getInstance(
        string $host,
        string $vhost,
        ?string $user,
        ?string $password,
        ?int $port = null,
    ): Client {
        if (null === self::$instance) {
            self::$instance = new Client([
                'host' => $host,
                'port' => $port,
                'vhost' => $vhost,
                'user' => $user,
                'password' => $password,
            ]);

            self::$instance->connect();
        }

        return self::$instance;
    }
}
