<?php

namespace App\Console\Commands;

use App\AI\Agents\GrammarAssistantAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

#[Signature('grammar')]
#[Description('Extract nouns, adjectives, and verbs from text')]
class GrammarCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $agent = new GrammarAssistantAgent;

        while (true) {
            $input = text(
                label: 'Enter sentence',
                placeholder: 'Type your sentence... (type "exit" to quit)',
                required: true,
            );

            if (in_array(strtolower(trim($input)), ['exit', 'quit'], true)) {
                $this->info('Goodbye!');

                return self::SUCCESS;
            }

            $response = $agent->prompt($input);

            if (is_array($response)) {
                $this->info(json_encode($response, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                continue;
            }

            $this->info((string) $response);
        }
    }
}
