<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('chat')]
#[Description('Chat with Claude')]
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
            callback: fn () => $this->callClaude($prompt),
            message: 'Thinking about that...'
        );

        $this->info($response);

        return self::SUCCESS;
    }

    /**
     * Call Claude API via Anthropic.
     */
    private function callClaude(string $prompt): string
    {
        try {
            $response = Http::withHeader('x-api-key', config('services.anthropic.key'))
                ->withHeader('anthropic-version', '2023-06-01')
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 1024,
                    'system' => 'You are a helpful assistant.',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ])
                ->throw()
                ->json();

            return $response['content'][0]['text'];
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to call Claude API: {$e->getMessage()}");
        }
    }
}
