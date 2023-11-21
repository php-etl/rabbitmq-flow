<?php

declare(strict_types=1);

namespace Kiboko\Component\Flow\RabbitMQ;

use Kiboko\Contract\Pipeline\StepCodeInterface;
use Kiboko\Contract\Pipeline\StepStateInterface;

final class State implements StepStateInterface
{
    private array $steps = [];
    private int $acceptMetric = 0;
    private int $rejectMetric = 0;
    private int $errorMetric = 0;

    public function __construct(
        private readonly StateManager $manager,
        private readonly string $stepCode,
        private readonly string $stepLabel,
    ) {
    }

    public function accept(int $count = 1): void
    {
        $this->acceptMetric += $count;

        $this->manager->trySend($this->stepCode);
    }

    public function reject(int $count = 1): void
    {
        $this->rejectMetric += $count;

        $this->manager->trySend($this->stepCode);
    }

    public function error(int $count = 1): void
    {
        $this->errorMetric += $count;

        $this->manager->trySend($this->stepCode);
    }

    public function toArray(): array
    {
        return [
            'code' => $this->stepCode,
            'label' => $this->stepLabel ?: $this->stepCode,
            'metrics' => iterator_to_array($this->walkMetrics()),
        ];
    }

    private function walkMetrics(): \Generator
    {
        if ($this->acceptMetric > 0) {
            yield [
                'code' => 'accept',
                'value' => $this->acceptMetric,
            ];
            $this->acceptMetric = 0;
        }
        if ($this->rejectMetric > 0) {
            yield [
                'code' => 'reject',
                'value' => $this->rejectMetric,
            ];
            $this->rejectMetric = 0;
        }
        if ($this->errorMetric > 0) {
            yield [
                'code' => 'error',
                'value' => $this->errorMetric,
            ];
            $this->errorMetric = 0;
        }
    }
}
