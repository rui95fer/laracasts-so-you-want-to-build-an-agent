<?php

namespace App\AI;

use App\AI\Tools\Tool;
use Illuminate\Support\Facades\Http;
use JsonException;

use function Laravel\Prompts\spin;

abstract class Agent
{
    /**
     * @return list<Tool>
     */
    abstract protected function tools(): array;

    protected function systemPrompt(): ?string
    {
        return null;
    }

    /**
     * Returns the JSON schema definition for structured output, or null for plain text.
     *
     * @return array<string, mixed>|null
     */
    protected function outputSchema(): ?array
    {
        return null;
    }

    /**
     * Run the agent loop and return a typed response.
     *
     * @param  list<array<string, mixed>>  $history
     */
    public function run(array &$history): AgentResponse
    {
        return $this->runAgentLoop($history);
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function runAgentLoop(array &$history): AgentResponse
    {
        $toolRunner = new ToolRunner($this->tools());

        while (true) {
            $response = spin(
                callback: fn () => $this->runModel($history),
                message: 'Thinking about that...',
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
                return $this->extractResponse($output);
            }

            $toolRunner->runAll($functionCalls, $history);
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
            ->post('https://api.openai.com/v1/responses', $this->buildRequestPayload($history))
            ->throw()
            ->json();
    }

    /**
     * Build the full API request payload, including optional system prompt,
     * tool definitions, and structured output schema.
     *
     * @param  list<array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    protected function buildRequestPayload(array $history): array
    {
        $payload = [
            'model' => config('services.openai.model', 'gpt-5.4-nano'),
            'input' => $history,
        ];

        $systemPrompt = $this->systemPrompt();
        if ($systemPrompt !== null) {
            $payload['instructions'] = $systemPrompt;
        }

        $toolDefinitions = (new ToolRunner($this->tools()))->definitions();
        if ($toolDefinitions !== []) {
            $payload['tools'] = $toolDefinitions;
        }

        $schema = $this->outputSchema();
        if ($schema !== null) {
            $payload['text'] = [
                'format' => [
                    'type' => 'json_schema',
                    ...$schema,
                ],
            ];
        }

        return $payload;
    }

    /**
     * Convert the raw output array into a typed AgentResponse.
     * Decodes JSON automatically when an output schema is configured.
     *
     * @param  list<array<string, mixed>>  $output
     */
    protected function extractResponse(array $output): AgentResponse
    {
        $text = $this->extractText($output);

        if ($this->outputSchema() !== null) {
            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($text, true, flags: JSON_THROW_ON_ERROR);

                return AgentResponse::structured($data);
            } catch (JsonException) {
                // Fall through to plain text response
            }
        }

        return AgentResponse::text($text);
    }

    /**
     * Extract the plain text from a model output array.
     *
     * @param  list<array<string, mixed>>  $output
     */
    private function extractText(array $output): string
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
