<?php

namespace App\Console\Commands;

use App\AI\Agents\ChatbotAgent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $agent = new ChatbotAgent;

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

            $response = $agent->prompt($prompt);

            if (is_array($response)) {
                $this->info((string) ($response['response'] ?? json_encode($response, JSON_THROW_ON_ERROR)));

                continue;
            }

            $this->info((string) $response);
        }
    }
}
