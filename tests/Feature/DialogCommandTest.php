<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it keeps conversation history across turns', function () {
    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Nice to meet you, Sam.'],
                    ],
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Your name is Sam.'],
                    ],
                ],
            ],
        ]);

    $this->artisan('dialogue')
        ->expectsQuestion('What is on your mind?', 'My name is Sam.')
        ->expectsOutput('Nice to meet you, Sam.')
        ->expectsQuestion('What is on your mind?', 'What is my name?')
        ->expectsOutput('Your name is Sam.')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $firstRequest */
    $firstRequest = $requests[0][0];

    expect($firstRequest->url())->toBe('https://api.openai.com/v1/responses');
    expect($firstRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'My name is Sam.'],
    ]);

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'My name is Sam.'],
        [
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                ['type' => 'output_text', 'text' => 'Nice to meet you, Sam.'],
            ],
        ],
        ['role' => 'user', 'content' => 'What is my name?'],
    ]);
});

test('it exits without calling anthropic when user types exit immediately', function () {
    Http::fake();

    $this->artisan('dialogue')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertNothingSent();
});
