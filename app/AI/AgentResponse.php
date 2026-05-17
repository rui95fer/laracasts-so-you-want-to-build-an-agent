<?php

namespace App\AI;

class AgentResponse
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public readonly bool $isStructured,
        public readonly string $text,
        public readonly array $data,
    ) {}

    public static function text(string $text): static
    {
        return new static(false, $text, []);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function structured(array $data): static
    {
        return new static(true, '', $data);
    }
}
