<?php

namespace App\AI\Tools;

interface Tool
{
    /**
     * Get the tool definition for the OpenAI API.
     *
     * @return array<string, mixed>
     */
    public function definition(): array;

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function use(array $arguments = []): mixed;
}
