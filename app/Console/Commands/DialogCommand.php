<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('dialogue')]
#[Description('Have a dialogue with OpenAI')]
class DialogCommand extends Command
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

            if (in_array(strtolower(trim($prompt)), ['exit', 'quit'], true)) {
                $this->info('Goodbye!');

                break;
            }

            $history[] = [
                'role' => 'user',
                'content' => $prompt,
            ];

            $response = spin(
                callback: fn () => $this->runModel($history),
                message: 'Thinking about that...'
            );

            $history = [...$history, ...($response['output'] ?? [])];

            $this->info((string) ($response['output'][0]['content'][0]['text'] ?? 'No text response returned.'));
        }

        return self::SUCCESS;
    }

    /**
     * Call the OpenAI Responses API.
     *
     * @param  list<array<string, mixed>>  $history
     * @return array{output?: list<array<string, mixed>>}
     */
    private function runModel(array $history): array
    {
        return Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-nano'),
                'instructions' => 'You are a helpful assistant.',
                'input' => $history,
            ])
            ->throw()
            ->json();
    }
}
