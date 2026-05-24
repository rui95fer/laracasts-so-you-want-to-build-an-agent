<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\Process;

class RunBashScript implements Tool
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'run_bash_script',
            'description' => 'Run a shell command from the project root and return its output.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'command' => [
                        'type' => 'string',
                        'description' => 'The shell command to execute.',
                    ],
                ],
                'required' => ['command'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function use(array $arguments = []): string
    {
        $command = $arguments['command'] ?? null;

        if (! is_string($command) || trim($command) === '') {
            return 'The "command" argument is required.';
        }

        $process = Process::path(base_path())
            ->timeout(60)
            ->run($command);

        if (! $process->successful()) {
            $errorOutput = trim($process->errorOutput());
            $message = $errorOutput !== '' ? $errorOutput : 'The command failed with no error output.';
            $exitCode = $process->exitCode();

            return sprintf('Command failed (exit code %s): %s', $exitCode ?? 'unknown', $message);
        }

        $output = trim($process->output());
        $exitCode = $process->exitCode();

        return $output !== ''
            ? $output
            : sprintf('Command completed successfully with exit code %s and no output.', $exitCode ?? '0');
    }
}
