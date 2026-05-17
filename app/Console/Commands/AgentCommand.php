<?php

namespace App\Console\Commands;

use App\AI\Tools\CurrentTime;
use App\AI\Tools\ReadFile;
use App\AI\Tools\Revenue;
use App\AI\Tools\Tool;
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
        return collect($this->tools())
            ->map(fn (Tool $tool): array => $tool->definition())
            ->values()
            ->all();
    }

    /**
     * @return list<Tool>
     */
    private function tools(): array
    {
        return [
            new CurrentTime,
            new ReadFile,
            new Revenue,
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

            $output = $this->executeTool($call);

            $history[] = [
                'type' => 'function_call_output',
                'call_id' => $call['call_id'],
                'output' => $output,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $call
     */
    private function executeTool(array $call): string
    {
        $toolName = $call['name'] ?? null;
        $arguments = $this->decodeArguments($call);

        foreach ($this->tools() as $tool) {
            if ($tool->definition()['name'] === $toolName) {
                $result = $tool->use($arguments);

                return is_string($result) ? $result : json_encode($result, JSON_THROW_ON_ERROR);
            }
        }

        return sprintf('Tool [%s] is not supported.', $toolName ?? 'unknown');
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
