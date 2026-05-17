<?php

namespace App\AI\Agents;

use App\AI\Agent;
use App\AI\Tools\Tool;

class GrammarAssistantAgent extends Agent
{
    /**
     * @return list<Tool>
     */
    protected function tools(): array
    {
        return [];
    }

    protected function instructions(): ?string
    {
        return 'You are a grammar assistant. Extract nouns, adjectives, and verbs from the user sentence.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'nouns' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'adjectives' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'verbs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
            ],
            'required' => ['nouns', 'adjectives', 'verbs'],
            'additionalProperties' => false,
        ];
    }
}
