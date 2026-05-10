<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

#[Signature('dialog')]
#[Description('Have a dialog with Claude')]
class DialogCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        /** @var array<int, array{role: string, content: string}> $history */
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

            $assistantMessage = spin(
                callback: fn () => $this->callClaude($history),
                message: 'Thinking about that...'
            );

            $history[] = [
                'role' => 'assistant',
                'content' => $assistantMessage,
            ];

            $this->info($assistantMessage);
        }

        return self::SUCCESS;
    }

    /**
     * Call Claude API via Anthropic.
     */
    /**
     * @param  array<int, array{role: string, content: string}>  $history
     */
    private function callClaude(array $history): string
    {
        try {
            $response = Http::withHeader('x-api-key', config('services.anthropic.key'))
                ->withHeader('anthropic-version', '2023-06-01')
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-5-haiku-20241022',
                    'max_tokens' => 1024,
                    'system' => 'You are a helpful assistant.',
                    'messages' => $history,
                ])
                ->throw()
                ->json();

            return $response['content'][0]['text'] ?? 'No text response returned.';
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to call Claude API: {$e->getMessage()}");
        }
    }
}
