<?php

namespace App\AI\Tools;

class CurrentTime implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'get_current_time',
            'description' => 'Get the current server time as an ISO string.',
            'parameters' => [
                'type' => 'object',
                'properties' => [],
                'required' => [],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function use(array $arguments = []): string
    {
        return now()->toIso8601String();
    }
}
