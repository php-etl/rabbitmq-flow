<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Ramsey\Uuid\Uuid;

class StateManager
{
    private array $steps = [];
    private array $tearedDown = [];
    private int $messageCount = 0;
    private int $lineCount = 0;
    private readonly Channel $channel;

    public function __construct(
        private readonly Client $connection,
        private readonly string $topic,
        private readonly int $lineThreshold = 1000,
        private readonly ?string $exchange = null,
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

    public function __destruct()
    {
        $this->channel->close();
    }

    public static function withoutAuthentication(
        string $host,
        string $vhost,
        string $topic,
        ?string $exchange = null,
        ?int $port = null,
        ?int $lineThreshold = 1000,
    ): self {
        $connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost' => $vhost,
            'user' => 'guest',
            'password' => 'guest',
        ]);
        $connection->connect();

        return new self(connection: $connection, topic: $topic, lineThreshold: $lineThreshold, exchange: $exchange);
    }

    public static function withAuthentication(
        string $host,
        string $vhost,
        string $topic,
        string $user,
        string $password,
        ?string $exchange = null,
        ?int $port = null,
        ?int $lineThreshold = 1000,
    ): self {
        $connection = new Client([
            'host' => $host,
            'port' => $port,
            'vhost' => $vhost,
            'user' => $user,
            'password' => $password,
        ]);
        $connection->connect();

        return new self(connection: $connection, topic: $topic, lineThreshold: $lineThreshold, exchange: $exchange);
    }


    public function stepState(
       string $jobCode,
       string $stepCode,
    ): State {
        $this->steps[] = $state = new State($this, $jobCode, $stepCode);

        return $state;
    }

    public function trySend($count): void
    {
        $this->lineCount += $count;

        if ($this->lineCount >= $this->lineThreshold) {
            $this->sendUpdate();
            $this->lineCount = 0;
        }
    }

    public function teardown(State $step): void
    {
        $this->tearedDown[] = $step;

        if (\count($this->steps) <= \count($this->tearedDown)) {
            $this->sendUpdate();
            $this->lineCount = 0;

            $this->steps = [];
            $this->tearedDown = [];
        }
    }

    private function sendUpdate(): void
    {
        $date = new \DateTimeImmutable();

        $this->channel->publish(
            json_encode([
                'messageNumber' => ++$this->messageCount,
                'execution' => getenv('EXECUTION_ID'),
                'date' => ['date' => $date->format('c'), 'tz' => $date->getTimezone()->getName()],
                'stepsUpdates' => array_map(fn (State $step) => $step->toArray(), $this->steps),
            ], \JSON_THROW_ON_ERROR),
            [
                'type' => 'update',
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic
        );
    }
}
