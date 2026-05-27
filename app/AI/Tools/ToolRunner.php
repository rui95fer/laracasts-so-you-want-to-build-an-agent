<?php

namespace App\AI\Tools;

use Illuminate\Support\Collection;
use JsonException;

use function Laravel\Prompts\info;

class ToolRunner
{
    /**
     * @param  list<Tool>  $tools
     */
    public function __construct(private readonly array $tools) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function definitions(): array
    {
        return collect($this->tools)
            ->map(fn (Tool $tool): array => $tool->definition())
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $functionCalls
     * @param  list<array<string, mixed>>  $history
     */
    public function runAll(Collection $functionCalls, array &$history): void
    {
        foreach ($functionCalls as $call) {
            $history[] = [
                'type' => 'function_call_output',
                'call_id' => $call['call_id'],
                'output' => $this->runTool($call),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $call
     */
    public function runTool(array $call): string
    {
        $toolName = $call['name'] ?? 'unknown';
        info("Running tool: {$toolName}(...) ");

        $arguments = $this->decodeArguments($call);

        foreach ($this->tools as $tool) {
            if ($tool->definition()['name'] !== $toolName) {
                continue;
            }

            $result = $tool->use($arguments);

            return is_string($result) ? $result : json_encode($result, JSON_THROW_ON_ERROR);
        }

        return sprintf('Tool [%s] is not supported.', $toolName);
    }

    /**
     * @param  array<string, mixed>  $call
     * @return array<string, mixed>
     */
    private function decodeArguments(array $call): array
    {
        $arguments = $call['arguments'] ?? '{}';

        if (! is_string($arguments)) {
            return [];
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($arguments, true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            return [];
        }
    }
}
