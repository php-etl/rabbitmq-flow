<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\StateInterface;

final class State implements StateInterface
{
    private Channel $channel;
    private array $metrics = [];

    public function __construct(
        private Client $connection,
        private string $pipelineId,
        private string $stepCode,
        private string $stepLabel,
        private string $topic,
        private ?int $messageLimit = 1,
        private ?string $exchange = null,
    ) {
        $this->channel = $this->connection->channel();
    }

    public static function withoutAuthentication(
        string $pipelineId,
        string $stepCode,
        string $stepLabel,
        string $host,
        string $vhost,
        string $topic,
        ?int $messageLimit = 1,
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

        return new self($connection, pipelineId: $pipelineId, stepCode: $stepCode, stepLabel: $stepLabel, topic: $topic, messageLimit: $messageLimit, exchange: $exchange);
    }

    public static function withAuthentication(
        string $pipelineId,
        string $stepCode,
        string $stepLabel,
        string $host,
        string $vhost,
        string $topic,
        ?string $user,
        ?string $password,
        ?int $messageLimit = 1,
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

        return new self($connection, pipelineId: $pipelineId, stepCode: $stepCode, stepLabel: $stepLabel, topic: $topic, messageLimit: $messageLimit, exchange: $exchange);
    }

    public function initialize(): void
    {
        $this->metrics = [
            'accept' => 0,
            'reject' => 0,
            'error' => 0,
        ];

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
        $this->metrics['accept'] += $step;

        if ($this->metrics['accept'] === $this->messageLimit) {
            $this->sendUpdate();

            $this->metrics['accept'] = 0;
            $this->metrics['reject'] = 0;
            $this->metrics['error'] = 0;
        }
    }

    public function reject(int $step = 1): void
    {
        $this->metrics['reject'] += $step;
    }

    public function error(int $step = 1): void
    {
        $this->metrics['error'] += $step;
    }

    public function teardown(): void
    {
        $this->sendUpdate();

        $this->channel->close();
        $this->connection->stop();
    }

    private function sendUpdate(): void
    {
        $date = new \DateTime();

        $this->channel->publish(
            \json_encode([
                'id' => $this->pipelineId,
                'date' => ['date' => $date->format('c'), 'tz' => $date->getTimezone()->getName()],
                'stepsUpdates' => [
                    [
                        'code' => $this->stepCode,
                        'label' => $this->stepLabel ?: $this->stepCode,
                        'metrics' => [
                            [
                                'code' => 'accept',
                                'value' => $this->metrics['accept']
                            ],
                            [
                                'code' => 'reject',
                                'value' => $this->metrics['reject']
                            ],
                            [
                                'code' => 'error',
                                'value' => $this->metrics['error']
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
}
