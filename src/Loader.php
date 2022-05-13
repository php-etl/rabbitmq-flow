<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Pipeline\LoaderInterface;

final class Loader implements LoaderInterface
{
    private Channel $channel;

    public function __construct(
        private Client $connection,
        private string $topic,
        private ?string $exchange = null,
    ) {
        $this->channel = $this->connection->channel();

        $this->channel->queueDeclare(
            queue: $this->topic,
            passive: false,
            durable: true,
            exclusive: false,
            autoDelete: true,
        );
    }

    public static function withoutAuthentication(
        string $host,
        string $vhost,
        string $topic,
        ?string $exchange = null,
        ?int $port = null,
    ): self {
        $connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost'  => $vhost,
            'user' => 'guest',
            'password' => 'guest',
        ]);
        $connection->connect();

        return new self($connection, topic: $topic, exchange: $exchange);
    }

    public static function withAuthentication(
        string $host,
        string $vhost,
        string $topic,
        ?string $user,
        ?string $password,
        ?string $exchange = null,
        ?int $port = null,
    ): self {
        $connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost'  => $vhost,
            'user' => $user,
            'password' => $password,
        ]);
        $connection->connect();

        return new self($connection, topic: $topic, exchange: $exchange);
    }

    public function load(): \Generator
    {
        $line = yield;

        while (true) {
            $this->channel->publish(
                \json_encode($line, JSON_THROW_ON_ERROR),
                [
                    'content-type' => 'application/json',
                ],
                $this->topic,
                $this->exchange,
            );

            $line = yield new AcceptanceResultBucket($line);
        }
    }
}
