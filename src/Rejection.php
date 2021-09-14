<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\RejectionInterface;

final class Rejection implements RejectionInterface
{
    private Client $connection;
    private Channel $channel;

    public function __construct(
        string $host,
        string $vhost,
        private string $topic,
        ?string $user = 'guest',
        ?string $password = 'guest',
        private ?string $exchange = null,
        ?int $port = null,
    ) {
        $this->connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost'  => $vhost,
            'user' => $user,
            'password' => $password,
        ]);
        $this->connection->connect();

        $this->channel = $this->connection->channel();
    }

    public static function withoutAuthentication(
        string $host,
        string $vhost,
        string $topic,
        ?string $exchange = null,
        ?int $port = null,
    ): self {
        return new self(host: $host, vhost: $vhost, topic: $topic, exchange: $exchange, port: $port);
    }

    public function reject(object|array $rejection, ?\Throwable $exception = null): void
    {
        $this->channel->publish(
            \json_encode([
                'item' => $rejection,
                'exception' => $exception,
            ]),
            [
                'content-type' => 'application/json',
            ],
            $this->topic,
            $this->exchange,
        );
    }
}
