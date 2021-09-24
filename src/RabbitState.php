<?php declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Contract\Pipeline\StateInterface;

final class RabbitState implements StateInterface
{
    private Client $connection;
    private Channel $channel;
    private \DateTimeInterface $date;
    private string $stepUuid;

    public function __construct(
        private string $host,
        private string $user,
        private string $password,
        private string $topic,
        private ?int $port = null,
        private ?string $vhost = null,
        private ?string $exchange = null,
    ) {
        $this->date = new \DateTime();
        $this->stepUuid = $this->generateRandomUuid();

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
