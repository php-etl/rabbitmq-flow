<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\StateInterface;

final class State implements StateInterface
{
    private Channel $channel;

    public function __construct(
        Client $connection,
        private string $stepUuid,
        private string $topic,
        private ?string $exchange = null,
    ) {
        $this->channel = $connection->channel();
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
            'vhost'  => $vhost,
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
            'vhost'  => $vhost,
            'user' => $user,
            'password' => $password,
        ]);
        $connection->connect();

        return new self($connection, stepUuid: $stepUuid, topic: $topic, exchange: $exchange);
    }

    public function initialize(int $start = 0): void
    {
        $this->channel->queueDeclare(
            queue: $this->topic,
            passive: false,
            durable: true,
            exclusive: false,
            autoDelete: true,
        );
    }

    public function accept(int $step = 1): void
    {
        $this->channel->publish(
            \json_encode([
                'id' => $this->generateRandomUuid(),
                'date' => ['date' => $this->date->format('c'), 'tz' => $this->date->getTimezone()->getName()],
                'stepsUpdates' => [
                    [
                        'code' => $this->stepUuid,
                        'measurements' => [
                            'increment' => [
                                'code' => $this->generateRandomUuid(),
                                'increment' => $step
                            ]
                        ]
                    ]
                ]
            ]),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic
        );
    }

    public function reject(int $step = 1): void
    {
        $this->channel->publish(
            \json_encode([
                'id' => $this->generateRandomUuid(),
                'date' => ['date' => $this->date->format('c'), 'tz' => $this->date->getTimezone()->getName()],
                'stepsUpdates' => [
                    [
                        'code' => $this->stepUuid,
                        'measurements' => [
                            'decrement' => [
                                'code' => $this->generateRandomUuid(),
                                'decrement' => $step
                            ]
                        ]
                    ]
                ]
            ]),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic
        );
    }

    private function generateRandomUuid(): string
    {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->stop();
    }
}
