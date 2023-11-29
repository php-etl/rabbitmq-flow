<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Bunny\Channel;
use Bunny\Client;
use Kiboko\Component\Bucket\AcceptanceResultBucket;
use Kiboko\Contract\Pipeline\ExtractorInterface;

final readonly class Extractor implements ExtractorInterface
{
    private Channel $channel;

    public function __construct(
        private Client $connection,
        private string $topic,
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
        string $host,
        string $vhost,
        string $topic,
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

        return new self($connection, topic: $topic);
    }

    public static function withAuthentication(
        string $host,
        string $vhost,
        string $topic,
        ?string $user,
        ?string $password,
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

        return new self($connection, topic: $topic);
    }

    public function extract(): iterable
    {
        while (true) {
            $message = $this->channel->get($this->topic);
            if (null === $message) {
                break;
            }
            $this->channel->ack($message);

            yield new AcceptanceResultBucket(\json_decode($message->content, true, 512, \JSON_THROW_ON_ERROR));
        }
    }
}
