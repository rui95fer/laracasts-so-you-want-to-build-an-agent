<?php

use App\AI\Agents\Agent;

afterEach(function () {
    if (file_exists(base_path('larry.md'))) {
        unlink(base_path('larry.md'));
    }
});

it('prepares default agent instructions', function () {
    $agent = new class extends Agent
    {
        protected function tools(): array
        {
            return [];
        }

        protected function schema(): ?array
        {
            return null;
        }
    };

    expect($agent->instructions())->toBe('You are a helpful AI assistant.');
});

it('optionally loads larry.md guidelines', function () {
    $agent = new class extends Agent
    {
        protected function tools(): array
        {
            return [];
        }

        protected function schema(): ?array
        {
            return null;
        }
    };

    file_put_contents(base_path('larry.md'), 'Use Pest instead of PHP Unit.');

    expect($agent->instructions())
        ->toContain('You are a helpful AI assistant.')
        ->toContain('## Project Guidelines')
        ->toContain('Use Pest instead of PHP Unit.');
});

it('allows subclasses to override persona', function () {
    $agent = new class extends Agent
    {
        protected function persona(): string
        {
            return 'You are a sarcastic coding assistant.';
        }

        protected function tools(): array
        {
            return [];
        }

        protected function schema(): ?array
        {
            return null;
        }
    };

    expect($agent->instructions())->toBe('You are a sarcastic coding assistant.');
});

it('includes guidelines when subclass overrides persona', function () {
    $agent = new class extends Agent
    {
        protected function persona(): string
        {
            return 'You are a sarcastic coding assistant.';
        }

        protected function tools(): array
        {
            return [];
        }

        protected function schema(): ?array
        {
            return null;
        }
    };

    file_put_contents(base_path('larry.md'), 'Use Pest instead of PHP Unit.');

    expect($agent->instructions())
        ->toContain('You are a sarcastic coding assistant.')
        ->toContain('## Project Guidelines')
        ->toContain('Use Pest instead of PHP Unit.');
});
