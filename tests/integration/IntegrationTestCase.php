<?php

namespace integration\Kiboko\Component\Flow\RabbitMQ;

use PHPUnit\Framework\TestCase;

class IntegrationTestCase extends TestCase
{
    public function testIfJsonIsCorrect()
    {
        $array = [
            'id' => '7bbbf1bd-9f17-4334-855d-ef77747fbc72',
            'date' => new \DateTime('now'),
            'stepsUpdates' => [
                [
                    'code' => '90ca518b-5d89-4088-a453-ab0c4779aa2d',
                    'measurements' => [
                        'increment' => [
                            'code' => '752602f1-a184-454f-bc48-c745ca7d9241',
                            'increment' => 1
                        ]
                    ]
                ]
            ]
        ];

        $this->assertArrayHasKey('id', $array);
    }
}
