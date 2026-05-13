<?php

namespace App\AI\Tools;

use Illuminate\Support\Str;

class ReadFile implements Tool
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'read_file',
            'description' => 'Read a file from the project root.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'The relative file path to read.',
                    ],
                ],
                'required' => ['path'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function use(array $arguments = []): string
    {
        $path = $arguments['path'] ?? null;

        if (! is_string($path) || trim($path) === '') {
            return 'The "path" argument is required.';
        }

        $resolvedPath = realpath(base_path($path));

        if ($resolvedPath === false || ! is_file($resolvedPath)) {
            return sprintf('Unable to read [%s].', $path);
        }

        if (! $this->isWithinProjectRoot($resolvedPath)) {
            return sprintf('The path [%s] is outside the project root.', $path);
        }

        $contents = file_get_contents($resolvedPath);

        if ($contents === false) {
            return sprintf('Unable to read [%s].', $path);
        }

        return $contents;
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
