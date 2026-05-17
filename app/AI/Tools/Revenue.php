<?php

namespace App\AI\Tools;

class Revenue implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'site_revenue',
            'description' => 'Get site revenue for a period.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'period' => [
                        'type' => 'string',
                        'description' => 'Revenue period to fetch.',
                        'enum' => ['daily', 'monthly', 'quarterly', 'yearly'],
                    ],
                ],
                'required' => ['period'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function use(array $arguments = []): string
    {
        return match ($arguments['period'] ?? null) {
            'daily' => '900',
            'monthly' => '18000',
            'quarterly' => '120000',
            'yearly' => '850000',
            default => 'unknown period',
        };
    }
}
