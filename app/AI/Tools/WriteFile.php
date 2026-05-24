<?php

namespace App\AI\Tools;

use Illuminate\Support\Str;

class WriteFile implements Tool
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'write_file',
            'description' => 'Write content to a file in the project root.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'The relative file path to write.',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'The content to write.',
                    ],
                    'mode' => [
                        'type' => 'string',
                        'description' => 'Use "overwrite" to replace content or "append" to add to an existing file.',
                        'enum' => ['overwrite', 'append'],
                    ],
                ],
                'required' => ['path', 'content'],
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
        $path = $arguments['path'] ?? null;
        $content = $arguments['content'] ?? null;
        $mode = $arguments['mode'] ?? 'overwrite';

        if (! is_string($path) || trim($path) === '') {
            return 'The "path" argument is required.';
        }

        if (! is_string($content)) {
            return 'The "content" argument must be a string.';
        }

        if (! is_string($mode) || ! in_array($mode, ['overwrite', 'append'], true)) {
            return 'The "mode" argument must be "overwrite" or "append".';
        }

        $absolutePath = base_path($path);
        $directoryPath = dirname($absolutePath);
        $resolvedDirectory = realpath($directoryPath);

        if (! is_dir($directoryPath) && ! mkdir($directoryPath, 0777, true) && ! is_dir($directoryPath)) {
            return sprintf('Unable to create directory for [%s].', $path);
        }

        if ($resolvedDirectory === false) {
            $resolvedDirectory = realpath($directoryPath);
        }

        if ($resolvedDirectory === false || ! $this->isWithinProjectRoot($resolvedDirectory)) {
            return sprintf('The path [%s] is outside the project root.', $path);
        }

        $flags = $mode === 'append' ? FILE_APPEND : 0;
        $bytesWritten = file_put_contents($absolutePath, $content, $flags);

        if ($bytesWritten === false) {
            return sprintf('Unable to write to [%s].', $path);
        }

        return sprintf('Wrote %d bytes to [%s].', $bytesWritten, $path);
    }

    private function isWithinProjectRoot(string $resolvedPath): bool
    {
        $projectRoot = realpath(base_path());

        if ($projectRoot === false) {
            return false;
        }

        $normalizedProjectRoot = $this->normalizePath($projectRoot);
        $normalizedResolvedPath = $this->normalizePath($resolvedPath);

        return $normalizedResolvedPath === $normalizedProjectRoot
            || Str::startsWith($normalizedResolvedPath, $normalizedProjectRoot.'/');
    }

    private function normalizePath(string $path): string
    {
        return Str::of($path)
            ->replace('\\', '/')
            ->lower()
            ->trim('/')
            ->toString();
    }
}
