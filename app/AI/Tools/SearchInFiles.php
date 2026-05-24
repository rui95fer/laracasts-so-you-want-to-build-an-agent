<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SearchInFiles implements Tool
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'search_in_files',
            'description' => 'Search for text in files under a project directory.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'The text to search for.',
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'The relative directory path to search. Defaults to project root.',
                    ],
                    'case_sensitive' => [
                        'type' => 'boolean',
                        'description' => 'Whether the search should be case sensitive. Defaults to false.',
                    ],
                    'max_results' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of line matches to return. Defaults to 20.',
                    ],
                ],
                'required' => ['query'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|string
     */
    public function use(array $arguments = []): array|string
    {
        $query = $arguments['query'] ?? null;
        $path = $arguments['path'] ?? '.';
        $caseSensitive = $arguments['case_sensitive'] ?? false;
        $maxResults = $arguments['max_results'] ?? 20;

        if (! is_string($query) || trim($query) === '') {
            return 'The "query" argument is required.';
        }

        if (! is_string($path) || trim($path) === '') {
            return 'The "path" argument must be a non-empty string.';
        }

        if (! is_bool($caseSensitive)) {
            $caseSensitive = false;
        }

        if (! is_int($maxResults) || $maxResults < 1) {
            $maxResults = 20;
        }

        $resolvedPath = realpath(base_path($path));

        if ($resolvedPath === false || ! is_dir($resolvedPath)) {
            return sprintf('Unable to search directory [%s].', $path);
        }

        if (! $this->isWithinProjectRoot($resolvedPath)) {
            return sprintf('The path [%s] is outside the project root.', $path);
        }

        $matches = [];

        foreach (File::allFiles($resolvedPath) as $fileInfo) {
            if (count($matches) >= $maxResults) {
                break;
            }

            $absolutePath = $fileInfo->getRealPath() ?: $fileInfo->getPathname();
            $lines = @file($absolutePath, FILE_IGNORE_NEW_LINES);

            if (! is_array($lines)) {
                continue;
            }

            foreach ($lines as $lineNumber => $line) {
                if (! is_string($line)) {
                    continue;
                }

                $containsQuery = $caseSensitive
                    ? Str::contains($line, $query)
                    : Str::contains(Str::lower($line), Str::lower($query));

                if (! $containsQuery) {
                    continue;
                }

                $matches[] = [
                    'path' => $this->toRelativePath($absolutePath),
                    'line' => $lineNumber + 1,
                    'content' => $line,
                ];

                if (count($matches) >= $maxResults) {
                    break;
                }
            }
        }

        return [
            'query' => $query,
            'path' => $this->toRelativePath($resolvedPath),
            'matches' => $matches,
        ];
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

    private function toRelativePath(string $path): string
    {
        $projectRoot = realpath(base_path());

        if ($projectRoot === false) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', $path);
        $normalizedRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');

        if ($normalizedPath === $normalizedRoot) {
            return '.';
        }

        return ltrim(Str::after($normalizedPath, $normalizedRoot), '/');
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
