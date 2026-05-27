<?php

namespace App\AI\Agents;

use App\AI\Attributes\CompactsAfter;
use App\AI\Tools\Tool;
use App\AI\Tools\ToolRunner;
use Illuminate\Support\Facades\Http;
use JsonException;
use ReflectionClass;

use function Laravel\Prompts\spin;

abstract class Agent
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $history = [];

    /**
     * @return list<Tool>
     */
    abstract protected function tools(): array;

    /**
     * @return array<string, mixed>|null
     */
    abstract protected function schema(): ?array;

    protected function persona(): string
    {
        return 'You are a helpful AI assistant.';
    }

    public function instructions(): string
    {
        $instructions = $this->persona();

        if (file_exists($guidelinesPath = base_path('larry.md'))) {
            $instructions .= "\n\n## Project Guidelines\n\n".file_get_contents($guidelinesPath);
        }

        return $instructions;
    }

    protected function getThreshold(): int
    {
        $reflection = new ReflectionClass($this);
        $attributes = $reflection->getAttributes(CompactsAfter::class);

        if (! empty($attributes)) {
            $attribute = $attributes[0]->newInstance();

            return $attribute->threshold;
        }

        return 30; // Default threshold
    }

    protected function shouldCompact(): bool
    {
        return count($this->history) >= $this->getThreshold();
    }

    protected function compact(): void
    {
        $response = spin(
            callback: fn () => Http::withToken(config('services.openai.key'))
                ->connectTimeout(10)
                ->timeout(30)
                ->post('https://api.openai.com/v1/responses', [
                    'model' => config('services.openai.model', 'gpt-5.4-nano'),
                    'instructions' => 'Summarize the following conversation history concisely, preserve the key facts and decisions, tool results, unresolved questions, omit pleasantries and redundant exchanges.',
                    'input' => $this->history,
                ])
                ->throw()
                ->json(),
            message: 'Compacting conversation history...',
        );

        $summary = $this->extractText($response['output'] ?? []);

        $this->history = [
            [
                'role' => 'user',
                'content' => "Earlier conversation summary:\n{$summary}",
            ],
        ];
    }

    public function prompt(string $prompt): mixed
    {
        $this->maybeCompact();

        $this->history[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $this->run();
    }

    protected function maybeCompact(): void
    {
        if ($this->shouldCompact()) {
            $this->compact();
        }
    }

    protected function run(): mixed
    {
        $toolRunner = new ToolRunner($this->tools());

        while (true) {
            $response = spin(
                callback: fn () => $this->runModel(),
                message: 'Thinking about that...',
            );

            $output = $response['output'] ?? [];

            $this->history = [
                ...$this->history,
                ...$output,
            ];

            $functionCalls = collect($output)
                ->filter(fn (array $item): bool => ($item['type'] ?? null) === 'function_call')
                ->values();

            if ($functionCalls->isEmpty()) {
                return $this->extractResponse($output);
            }

            $toolRunner->runAll($functionCalls, $this->history);
        }
    }

    /**
     * @return array{output?: list<array<string, mixed>>}
     */
    protected function runModel(): array
    {
        return Http::withToken(config('services.openai.key'))
            ->connectTimeout(10)
            ->timeout(30)
            ->post('https://api.openai.com/v1/responses', $this->buildRequestPayload())
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestPayload(): array
    {
        $payload = [
            'model' => config('services.openai.model', 'gpt-5.4-nano'),
            'input' => $this->history,
            'instructions' => $this->instructions(),
            'tools' => (new ToolRunner($this->tools()))->definitions(),
        ];

        $schema = $this->schema();
        if ($schema !== null) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'agent_response',
                    'strict' => true,
                    'schema' => $schema,
                ],
            ];
        }

        return $payload;
    }

    /**
     * @param  list<array<string, mixed>>  $output
     */
    protected function extractResponse(array $output): mixed
    {
        $text = $this->extractText($output);

        if ($this->schema() === null) {
            return $text;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            return [
                'response' => $text,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $output
     */
    protected function extractText(array $output): string
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
