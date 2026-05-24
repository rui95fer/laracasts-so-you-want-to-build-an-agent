<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('chat')]
#[Description('Chat with OpenAI')]
class ChatCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $prompt = text(
            label: 'What is on your mind?',
            placeholder: 'Enter your question...',
            required: true,
        );

        $response = spin(
            callback: fn () => $this->runModel($prompt),
            message: 'Thinking about that...'
        );

        $this->info($response);

        return self::SUCCESS;
    }

    /**
     * Call the OpenAI Responses API.
     */
    private function runModel(string $prompt): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-nano'),
                'instructions' => 'You are a helpful assistant.',
                'input' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ])
            ->throw()
            ->json();

        return (string) ($response['output'][0]['content'][0]['text'] ?? 'No text response returned.');
    }
}
