<?php

namespace App\AI;

use App\AI\Tools\Tool;
use Illuminate\Support\Facades\Http;
use JsonException;

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

    protected function instructions(): ?string
    {
        return null;
    }

    public function prompt(string $prompt): mixed
    {
        $this->history[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        return $this->run();
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
            'tools' => (new ToolRunner($this->tools()))->definitions(),
        ];

        $instructions = $this->instructions();
        if ($instructions !== null) {
            $payload['instructions'] = $instructions;
        }

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
