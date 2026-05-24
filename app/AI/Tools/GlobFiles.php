<?php

namespace App\AI\Tools;

use Illuminate\Support\Str;

class GlobFiles implements Tool
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'glob_files',
            'description' => 'Find files and directories in the project using a glob pattern.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'pattern' => [
                        'type' => 'string',
                        'description' => 'A glob pattern relative to the project root, like "app/**/*.php".',
                    ],
                    'max_results' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of matches to return. Defaults to 100.',
                    ],
                ],
                'required' => ['pattern'],
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
        $pattern = $arguments['pattern'] ?? null;
        $maxResults = $arguments['max_results'] ?? 100;

        if (! is_string($pattern) || trim($pattern) === '') {
            return 'The "pattern" argument is required.';
        }

        if (! is_int($maxResults) || $maxResults < 1) {
            $maxResults = 100;
        }

        $rawMatches = glob(base_path($pattern), GLOB_BRACE) ?: [];

        $matches = collect($rawMatches)
            ->filter(fn (string $path): bool => $this->isWithinProjectRoot($path))
            ->map(fn (string $path): string => $this->toRelativePath($path))
            ->sort()
            ->values()
            ->take($maxResults)
            ->all();

        return [
            'pattern' => $pattern,
            'matches' => $matches,
        ];
    }

    private function isWithinProjectRoot(string $path): bool
    {
        $resolvedPath = realpath($path);
        $projectRoot = realpath(base_path());

        if ($resolvedPath === false || $projectRoot === false) {
            return false;
        }

        $normalizedProjectRoot = $this->normalizePath($projectRoot);
        $normalizedResolvedPath = $this->normalizePath($resolvedPath);

        return $normalizedResolvedPath === $normalizedProjectRoot
            || Str::startsWith($normalizedResolvedPath, $normalizedProjectRoot.'/');
    }

    private function toRelativePath(string $path): string
    {
        $resolvedPath = realpath($path);
        $projectRoot = realpath(base_path());

        if ($resolvedPath === false || $projectRoot === false) {
            return $path;
        }

        $normalizedPath = str_replace('\\', '/', $resolvedPath);
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
