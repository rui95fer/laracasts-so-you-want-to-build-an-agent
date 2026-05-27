<?php

namespace App\AI\Memory;

class Store
{
    public function fetch(): string
    {
        $path = $this->path();

        if (! file_exists($path)) {
            return '';
        }

        return @file_get_contents($path) ?: '';
    }

    public function update(string $memory): void
    {
        file_put_contents($this->path(), $memory, LOCK_EX);
    }

    protected function path(): string
    {
        return storage_path('larry-memory.md');
    }
}
