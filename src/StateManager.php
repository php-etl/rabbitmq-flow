<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;

class StateManager
{
    /** @var array<string, State> */
    private array $steps = [];
    /** @var list<State> */
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
        $channel = $this->connection->channel();
        if (!$channel instanceof Channel) {
            throw new \RuntimeException('Expected Channel from Bunny client');
        }
        $this->channel = $channel;

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

    public function stepState(string $stepCode, string $stepLabel): State
    {
        return $this->steps[$stepCode] = new State($this, $stepCode, $stepLabel);
    }

    public function trySend(int $count): void
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
            \json_encode([
                'messageNumber' => ++$this->messageCount,
                'id' => \uuid_create(UUID_TYPE_RANDOM),
                'date' => ['date' => $date->format('c'), 'tz' => $date->getTimezone()->getName()],
                'stepsUpdates' => \array_map(fn (State $step) => $step->toArray(), $this->steps),
            ], \JSON_THROW_ON_ERROR),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange ?? '',
            $this->topic
        );
    }
}
