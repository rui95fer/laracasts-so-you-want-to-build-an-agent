<?php

namespace App\AI\Agents;

use App\AI\Agent;
use App\AI\Tools\CurrentTime;
use App\AI\Tools\ReadFile;
use App\AI\Tools\Revenue;
use App\AI\Tools\Tool;

class ChatbotAgent extends Agent
{
    /**
     * @return list<Tool>
     */
    protected function tools(): array
    {
        return [
            new CurrentTime,
            new ReadFile,
            new Revenue,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'response' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['response'],
            'additionalProperties' => false,
        ];
    }
}
