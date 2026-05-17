<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

function chatbotPayload(string $response): string
{
    return json_encode([
        'response' => $response,
    ], JSON_THROW_ON_ERROR);
}

test('it returns the assistant response when no tools are requested', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => chatbotPayload('Hello from the agent.')],
                ],
            ],
        ],
    ]);

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'Hello there')
        ->expectsOutput('Hello from the agent.')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(1);

    $requests = Http::recorded();

    /** @var Request $firstRequest */
    $firstRequest = $requests[0][0];

    expect($firstRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'Hello there'],
    ]);

    expect(collect($firstRequest['tools'])->pluck('name')->all())
        ->toBe(['get_current_time', 'read_file', 'site_revenue']);

    expect($firstRequest['text']['format']['type'])->toBe('json_schema');
    expect($firstRequest['text']['format']['schema']['required'])->toBe(['response']);
});

test('it runs the revenue tool before producing a final answer', function () {
    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'function_call',
                    'name' => 'site_revenue',
                    'call_id' => 'call_revenue',
                    'arguments' => json_encode(['period' => 'quarterly'], JSON_THROW_ON_ERROR),
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => chatbotPayload('Quarterly revenue is 120000.')],
                    ],
                ],
            ],
        ]);

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'How much did we make last quarter?')
        ->expectsOutput('Quarterly revenue is 120000.')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'How much did we make last quarter?'],
        [
            'type' => 'function_call',
            'name' => 'site_revenue',
            'call_id' => 'call_revenue',
            'arguments' => json_encode(['period' => 'quarterly'], JSON_THROW_ON_ERROR),
        ],
        [
            'type' => 'function_call_output',
            'call_id' => 'call_revenue',
            'output' => '120000',
        ],
    ]);
});

test('it runs the current time tool before producing a final answer', function () {
    CarbonImmutable::setTestNow('2026-05-13T12:34:56+00:00');

    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'function_call',
                    'name' => 'get_current_time',
                    'call_id' => 'call_time',
                    'arguments' => '{}',
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => chatbotPayload('It is 2026-05-13T12:34:56+00:00.')],
                    ],
                ],
            ],
        ]);

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'What time is it?')
        ->expectsOutput('It is 2026-05-13T12:34:56+00:00.')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'What time is it?'],
        [
            'type' => 'function_call',
            'name' => 'get_current_time',
            'call_id' => 'call_time',
            'arguments' => '{}',
        ],
        [
            'type' => 'function_call_output',
            'call_id' => 'call_time',
            'output' => '2026-05-13T12:34:56+00:00',
        ],
    ]);
});

test('it supports multiple tool calls in a single model response', function () {
    CarbonImmutable::setTestNow('2026-05-13T12:34:56+00:00');

    $relativePath = 'storage/framework/testing/agent-notes.txt';
    $directory = dirname(base_path($relativePath));

    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    file_put_contents(base_path($relativePath), 'Agent notes go here.');

    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'function_call',
                    'name' => 'get_current_time',
                    'call_id' => 'call_time',
                    'arguments' => '{}',
                ],
                [
                    'type' => 'function_call',
                    'name' => 'read_file',
                    'call_id' => 'call_file',
                    'arguments' => json_encode(['path' => $relativePath], JSON_THROW_ON_ERROR),
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => chatbotPayload('I checked both tools.')],
                    ],
                ],
            ],
        ]);

    try {
        $this->artisan('agent')
            ->expectsQuestion('What is on your mind?', 'Read the file and tell me the time.')
            ->expectsOutput('I checked both tools.')
            ->expectsQuestion('What is on your mind?', 'exit')
            ->expectsOutput('Goodbye!')
            ->assertSuccessful();
    } finally {
        @unlink(base_path($relativePath));
    }

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'])->toBe([
        ['role' => 'user', 'content' => 'Read the file and tell me the time.'],
        [
            'type' => 'function_call',
            'name' => 'get_current_time',
            'call_id' => 'call_time',
            'arguments' => '{}',
        ],
        [
            'type' => 'function_call',
            'name' => 'read_file',
            'call_id' => 'call_file',
            'arguments' => json_encode(['path' => $relativePath], JSON_THROW_ON_ERROR),
        ],
        [
            'type' => 'function_call_output',
            'call_id' => 'call_time',
            'output' => '2026-05-13T12:34:56+00:00',
        ],
        [
            'type' => 'function_call_output',
            'call_id' => 'call_file',
            'output' => 'Agent notes go here.',
        ],
    ]);
});

test('it exits immediately without calling the model', function () {
    Http::fake();

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertNothingSent();
});
