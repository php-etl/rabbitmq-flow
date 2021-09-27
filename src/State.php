<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\StateInterface;

final class State implements StateInterface
{
    private Channel $channel;
    private array $metrics;

    public function __construct(
        private Client $connection,
        private string $stepCode,
        private string $stepLabel,
        private string $topic,
        private ?string $exchange = null,
    ) {
        $this->channel = $this->connection->channel();
    }

    public static function withoutAuthentication(
        string $stepCode,
        string $stepLabel,
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

        return new self($connection, stepCode: $stepCode, stepLabel: $stepLabel, topic: $topic, exchange: $exchange);
    }

    public static function withAuthentication(
        string $stepCode,
        string $stepLabel,
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

        return new self($connection, stepCode: $stepCode, stepLabel: $stepLabel, topic: $topic, exchange: $exchange);
    }

    public function initialize(int $start = 0): void
    {
        $this->metrics = [
            'accept' => 0,
            'reject' => 0,
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

        $this->sendMessage('accept');
    }

    public function reject(int $step = 1): void
    {
        $this->metrics['reject'] += $step;

        $this->sendMessage('reject');
    }

    public function error(int $step = 1): void
    {
        // Not implemented yet
    }

    public function teardown(): void
    {
        $this->channel->close();
        $this->connection->stop();
    }

    private function sendMessage(string $metricCode): void
    {
        $date = new \DateTime();

        $this->channel->publish(
            \json_encode([
                'id' => '',
                'date' => ['date' => $date->format('c'), 'tz' => $date->getTimezone()->getName()],
                'stepsUpdates' => [
                    [
                        'code' => $this->stepCode,
                        'label' => $this->stepLabel ?: $this->stepCode,
                        'metrics' => [
                            [
                                'code' => $metricCode,
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
}
