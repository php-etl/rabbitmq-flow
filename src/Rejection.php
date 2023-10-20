<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\RejectionInterface;
use Kiboko\Contract\Pipeline\RejectionWithReasonInterface;

final readonly class Rejection implements RejectionInterface, RejectionWithReasonInterface
{
    private Channel $channel;

    public function __construct(
        private Client $connection,
        private string $stepUuid,
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
        string $stepUuid,
        string $host,
        string $vhost,
        string $topic,
        ?string $exchange = null,
        ?int $port = null,
    ): self {
        $connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost' => $vhost,
            'user' => 'guest',
            'password' => 'guest',
        ]);
        $connection->connect();

        return new self($connection, stepUuid: $stepUuid, topic: $topic, exchange: $exchange);
    }

    public static function withAuthentication(
        string $stepUuid,
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
            'vhost' => $vhost,
            'user' => $user,
            'password' => $password,
        ]);
        $connection->connect();

        return new self($connection, stepUuid: $stepUuid, topic: $topic, exchange: $exchange);
    }

    public function reject(object|array $rejection, ?\Throwable $exception = null): void
    {
        $this->channel->publish(
            json_encode([
                'item' => $rejection,
                'exception' => $exception,
                'step' => $this->stepUuid,
            ], \JSON_THROW_ON_ERROR),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic,
        );
    }

    public function rejectWithReason(object|array $rejection, string $reason, ?\Throwable $exception = null): void
    {
        $this->channel->publish(
            json_encode([
                'item' => $rejection,
                'reason' => $reason,
                'exception' => $exception,
                'step' => $this->stepUuid,
            ], \JSON_THROW_ON_ERROR),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic,
        );
    }

    public function initialize(): void
    {
        $this->channel->queueDeclare(
            queue: $this->topic,
            passive: false,
            durable: true,
            exclusive: false,
            autoDelete: true,
        );
    }

    public function teardown(): void
    {
        $this->channel->close();
        $this->connection->stop();
    }
}
