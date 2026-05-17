<?php

namespace App\AI\Agents;

use App\AI\Agent;

class GrammarAgent extends Agent
{
    protected function tools(): array
    {
        return [];
    }

    protected function systemPrompt(): string
    {
        return 'You are an expert grammar and writing assistant. Analyze the user\'s text for grammatical errors, spelling mistakes, and stylistic improvements. Return a structured response with all corrections.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function outputSchema(): array
    {
        return [
            'name' => 'grammar_check',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'corrected_text' => [
                        'type' => 'string',
                        'description' => 'The fully corrected version of the input text.',
                    ],
                    'has_errors' => [
                        'type' => 'boolean',
                        'description' => 'Whether any grammar or spelling errors were found.',
                    ],
                    'corrections' => [
                        'type' => 'array',
                        'description' => 'List of individual corrections made.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'original' => [
                                    'type' => 'string',
                                    'description' => 'The original incorrect phrase.',
                                ],
                                'corrected' => [
                                    'type' => 'string',
                                    'description' => 'The corrected phrase.',
                                ],
                                'explanation' => [
                                    'type' => 'string',
                                    'description' => 'Brief explanation of the correction.',
                                ],
                            ],
                            'required' => ['original', 'corrected', 'explanation'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'required' => ['corrected_text', 'has_errors', 'corrections'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }
}
