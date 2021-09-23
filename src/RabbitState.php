<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\StateInterface;

final class RabbitState implements StateInterface
{
    private Client $connection;
    private Channel $channel;
    private array $metrics;

    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $topic,
        private ?int $port = null,
        private ?string $vhost = null,
        private ?string $exchange = null,
    ) {
        $this->connection = new Client([
            'host' => $this->host,
            'vhost'  => $this->vhost,
            'port' => $this->port,
            'user' => $this->user,
            'password' => $this->password,
        ]);

        $this->connection->connect();
        $this->channel = $this->connection->channel();
        $this->channel->queueDeclare($this->topic);
    }

    public function initialize(int $start = 0): void
    {
        $this->metrics = [
            'accept' => 0,
            'reject' => 0,
        ];
    }

    public function accept(int $step = 1): void
    {
        $this->metrics['accept'] += $step;

        $this->channel->publish(
            \json_encode([
                'Line accepted : ' => $this->metrics['accept']
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
        $this->metrics['reject'] += $step;

        $this->channel->publish(
            \json_encode([
                'Line rejected : ' => $this->metrics['reject']
            ]),
            [
                'content-type' => 'application/json',
            ],
            $this->exchange,
            $this->topic
        );
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->stop();
    }
}
