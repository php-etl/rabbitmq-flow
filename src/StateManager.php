<?php

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
    private Channel $channel;

    public function __construct(
        private Client $connection,
        private string $topic,
        private int $lineThreshold = 1000,
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

    public function __destruct()
    {
        $this->channel->close();
    }

    public function stepState(
       State $state
    ): State {
        return $this->steps[] = $state;
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

        if (count($this->steps) <= count($this->tearedDown)) {
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
                'id' => Uuid::uuid4(),
                'date' => ['date' => $date->format('c'), 'tz' => $date->getTimezone()->getName()],
                'stepsUpdates' => array_map(fn (State $step) => $step->toArray(), $this->steps),
            ]),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic
        );
    }
}
