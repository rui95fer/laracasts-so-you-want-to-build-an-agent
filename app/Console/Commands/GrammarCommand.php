<?php

namespace App\Console\Commands;

use App\AI\Agents\GrammarAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('grammar')]
#[Description('Check and correct grammar in your text')]
class GrammarCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $agent = new GrammarAgent;

        while (true) {
            $input = text(
                label: 'Enter text to check',
                placeholder: 'Type your text... (type "exit" to quit)',
                required: true,
            );

            if (in_array(strtolower(trim($input)), ['exit', 'quit'], true)) {
                $this->info('Goodbye!');

                return self::SUCCESS;
            }

            /** @var list<array<string, mixed>> $history */
            $history = [
                ['role' => 'user', 'content' => $input],
            ];

            $response = $agent->run($history);

            if ($response->isStructured) {
                $this->displayGrammarResult($response->data);
            } else {
                $this->info($response->text);
            }
        }
    }

    /**
     * Render a grammar check result to the console.
     *
     * @param  array<string, mixed>  $data
     */
    private function displayGrammarResult(array $data): void
    {
        $hasErrors = $data['has_errors'] ?? false;
        $correctedText = $data['corrected_text'] ?? '';

        /** @var list<array{original: string, corrected: string, explanation: string}> $corrections */
        $corrections = $data['corrections'] ?? [];

        if (! $hasErrors) {
            $this->info('✓ No grammar errors found!');

            return;
        }

        $this->line('');
        $this->info('Corrected text:');
        $this->line($correctedText);

        if ($corrections !== []) {
            $this->line('');
            $this->info('Corrections:');
            $this->table(
                ['Original', 'Corrected', 'Explanation'],
                array_map(fn (array $correction): array => [
                    $correction['original'] ?? '',
                    $correction['corrected'] ?? '',
                    $correction['explanation'] ?? '',
                ], $corrections),
            );
        }
    }
}
