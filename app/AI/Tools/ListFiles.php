<?php

namespace App\AI\Tools;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ListFiles implements Tool
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'list_files',
            'description' => 'List files and directories for a relative path in the project root.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'path' => [
                        'type' => 'string',
                        'description' => 'The relative directory path to list. Defaults to project root.',
                    ],
                ],
                'required' => [],
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
        $path = $arguments['path'] ?? '.';

        if (! is_string($path) || trim($path) === '') {
            return 'The "path" argument must be a non-empty string.';
        }

        $resolvedPath = realpath(base_path($path));

        if ($resolvedPath === false || ! is_dir($resolvedPath)) {
            return sprintf('Unable to list directory [%s].', $path);
        }

        if (! $this->isWithinProjectRoot($resolvedPath)) {
            return sprintf('The path [%s] is outside the project root.', $path);
        }

        $directories = collect(File::directories($resolvedPath))
            ->map(fn (string $directory): string => $this->toRelativePath($directory).'/')
            ->sort()
            ->values()
            ->all();

        $files = collect(File::files($resolvedPath))
            ->map(fn (\SplFileInfo $fileInfo): string => $this->toRelativePath($fileInfo->getRealPath() ?: $fileInfo->getPathname()))
            ->sort()
            ->values()
            ->all();

        return [
            'path' => $this->toRelativePath($resolvedPath),
            'directories' => $directories,
            'files' => $files,
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
