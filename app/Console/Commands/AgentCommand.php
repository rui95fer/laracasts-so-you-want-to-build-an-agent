<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('agent')]
#[Description('Run a simple tool-using agent')]
class AgentCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var list<array<string, mixed>> $history */
        $history = [];

        while (true) {
            $prompt = text(
                label: 'What is on your mind?',
                placeholder: 'Enter your question... (type "exit" to quit)',
                required: true,
            );

            if (in_array(Str::lower(trim($prompt)), ['exit', 'quit'], true)) {
                $this->info('Goodbye!');

                return self::SUCCESS;
            }

            $history[] = [
                'role' => 'user',
                'content' => $prompt,
            ];

            $this->info($this->runAgentLoop($history));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function runAgentLoop(array &$history): string
    {
        while (true) {
            $response = spin(
                callback: fn () => $this->runModel($history),
                message: 'Thinking about that...'
            );

            $output = $response['output'] ?? [];

            $history = [
                ...$history,
                ...$output,
            ];

            $functionCalls = collect($output)
                ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'function_call')
                ->values();

            if ($functionCalls->isEmpty()) {
                return $this->extractAssistantMessage($output);
            }

            $this->runTools($functionCalls, $history);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @return array{output?: list<array<string, mixed>>}
     */
    private function runModel(array $history): array
    {
        return Http::withToken(config('services.openai.key'))
            ->connectTimeout(10)
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-nano'),
                'input' => $history,
                'tools' => $this->toolDefinitions(),
            ])
            ->throw()
            ->json();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return [
            [
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
            ],
            [
                'type' => 'function',
                'name' => 'read_file',
                'description' => 'Read a file from the project root.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'The relative file path to read.',
                        ],
                    ],
                    'required' => ['path'],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $functionCalls
     * @param  list<array<string, mixed>>  $history
     */
    private function runTools(Collection $functionCalls, array &$history): void
    {
        foreach ($functionCalls as $call) {
            info("Running tool: {$call['name']}(...) ");

            $history[] = [
                'type' => 'function_call_output',
                'call_id' => $call['call_id'],
                'output' => $this->executeToolCall($call),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $call
     */
    private function executeToolCall(array $call): string
    {
        return match ($call['name'] ?? null) {
            'get_current_time' => now()->toIso8601String(),
            'read_file' => $this->readProjectFile($call),
            default => sprintf('Tool [%s] is not supported.', $call['name'] ?? 'unknown'),
        };
    }

    /**
     * @param  array<string, mixed>  $call
     */
    private function readProjectFile(array $call): string
    {
        $arguments = $this->decodeArguments($call);
        $path = $arguments['path'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            return 'The "path" argument is required.';
        }

        $resolvedPath = realpath(base_path($path));

        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            return sprintf('Unable to read [%s].', $path);
        }

        if (! $this->isWithinProjectRoot($resolvedPath)) {
            return sprintf('The path [%s] is outside the project root.', $path);
        }

        $contents = file_get_contents($resolvedPath);

        if ($contents === false) {
            return sprintf('Unable to read [%s].', $path);
        }

        return $contents;
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

    private function isWithinProjectRoot(string $resolvedPath): bool
    {
        $projectRoot = realpath(base_path());

        if ($projectRoot === false) {
            return false;
        }

        $normalizedProjectRoot = $this->normalizePath($projectRoot);
        $normalizedResolvedPath = $this->normalizePath($resolvedPath);

        return $normalizedResolvedPath === $normalizedProjectRoot
            || Str::startsWith($normalizedResolvedPath, $normalizedProjectRoot.'/');
    }

    private function normalizePath(string $path): string
    {
        return Str::of($path)
            ->replace('\\', '/')
            ->lower()
            ->trim('/')
            ->toString();
    }

    /**
     * @param  list<array<string, mixed>>  $output
     */
    private function extractAssistantMessage(array $output): string
    {
        $message = collect($output)->first(fn (array $item): bool => ($item['type'] ?? null) === 'message');

        if (is_array($message)) {
            $text = collect($message['content'] ?? [])
                ->map(fn (array $content): ?string => $content['text'] ?? null)
                ->filter()
                ->implode("\n\n");

            if ($text !== '') {
                return $text;
            }
        }

        $text = collect($output)
            ->map(fn (array $item): ?string => ($item['type'] ?? null) === 'output_text' ? ($item['text'] ?? null) : null)
            ->filter()
            ->implode("\n\n");

        return $text !== '' ? $text : 'No text response returned.';
    }
}
