<?php

use App\AI\Agents\Agent;
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
        ->toBe([
            'get_current_time',
            'read_file',
            'site_revenue',
            'write_file',
            'run_bash_script',
            'list_files',
            'glob_files',
            'search_in_files',
        ]);

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

test('it runs the write file tool before producing a final answer', function () {
    $relativePath = 'storage/framework/testing/agent-write-file.txt';

    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'function_call',
                    'name' => 'write_file',
                    'call_id' => 'call_write_file',
                    'arguments' => json_encode([
                        'path' => $relativePath,
                        'content' => 'hello from tool',
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => chatbotPayload('File written.')],
                    ],
                ],
            ],
        ]);

    try {
        $this->artisan('agent')
            ->expectsQuestion('What is on your mind?', 'Create a file for me')
            ->expectsOutput('File written.')
            ->expectsQuestion('What is on your mind?', 'exit')
            ->expectsOutput('Goodbye!')
            ->assertSuccessful();

        expect(file_get_contents(base_path($relativePath)))->toBe('hello from tool');
    } finally {
        @unlink(base_path($relativePath));
    }

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'][2]['call_id'])->toBe('call_write_file');
    expect($secondRequest['input'][2]['output'])->toBe('Wrote 15 bytes to [storage/framework/testing/agent-write-file.txt].');
});

test('it runs the bash script tool before producing a final answer', function () {
    Http::fakeSequence()
        ->push([
            'output' => [
                [
                    'type' => 'function_call',
                    'name' => 'run_bash_script',
                    'call_id' => 'call_run_bash',
                    'arguments' => json_encode([
                        'command' => 'php artisan --version',
                    ], JSON_THROW_ON_ERROR),
                ],
            ],
        ])
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => chatbotPayload('I checked the Laravel version.')],
                    ],
                ],
            ],
        ]);

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'What Laravel version is this?')
        ->expectsOutput('I checked the Laravel version.')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(2);

    $requests = Http::recorded();

    /** @var Request $secondRequest */
    $secondRequest = $requests[1][0];

    expect($secondRequest['input'][2]['call_id'])->toBe('call_run_bash');
    expect($secondRequest['input'][2]['output'])->toContain('Laravel Framework');
});

test('it exits immediately without calling the model', function () {
    Http::fake();

    $this->artisan('agent')
        ->expectsQuestion('What is on your mind?', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertNothingSent();
});

test('it compacts conversation history when reaching the threshold', function () {
    // Create a test agent with a low threshold to trigger compaction
    $agentClass = new class extends Agent
    {
        protected function tools(): array
        {
            return [];
        }

        protected function schema(): ?array
        {
            return null;
        }

        protected function getThreshold(): int
        {
            return 4; // Low threshold for testing
        }
    };

    // Simulate a conversation that exceeds the threshold
    $agent = new $agentClass;

    Http::fakeSequence()
        // First prompt - agent responds (history: user1, message1 = 2 items)
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Hi there!'],
                    ],
                ],
            ],
        ])
        // Second prompt - agent responds (input: user1, message1, user2 = 3 items; after response: 4 items)
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Great question!'],
                    ],
                ],
            ],
        ])
        // Third prompt triggers compaction because count(history) = 4 >= threshold(4)
        // Compaction request - summarize history
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Summary: User greeted, agent greeted back. User asked question, agent answered.'],
                    ],
                ],
            ],
        ])
        // After compaction - third prompt proceeds (history: summary, user3)
        ->push([
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        ['type' => 'output_text', 'text' => 'Final answer!'],
                    ],
                ],
            ],
        ]);

    $agent->prompt('Hello');
    expect($agent->history)->toHaveCount(2); // user message + agent response

    $agent->prompt('How are you?');
    expect($agent->history)->toHaveCount(4); // Now we have 4 items (user1, msg1, user2, msg2)

    // This third prompt should trigger compaction because history count = 4 >= threshold
    $agent->prompt('Tell me more');

    $requests = Http::recorded();

    // First request - only first user message
    expect($requests[0][0]['input'])->toBe([
        ['role' => 'user', 'content' => 'Hello'],
    ]);

    // Second request - has 3 items (user1, message1, user2)
    // The message2 is added AFTER the HTTP request is sent
    expect($requests[1][0]['input'])->toHaveCount(3);
    expect($requests[1][0]['input'][0]['content'])->toBe('Hello');
    expect($requests[1][0]['input'][2]['content'])->toBe('How are you?');

    // Third request - is the compaction call, sent with history count = 4
    expect($requests[2][0]['instructions'])->toContain('Summarize the following conversation history');
    expect($requests[2][0]['input'])->toHaveCount(4);

    // Fourth request - user prompt after compaction, should have summary + new prompt
    expect($requests[3][0]['input'])->toHaveCount(2);
    expect($requests[3][0]['input'][0]['role'])->toBe('user');
    expect($requests[3][0]['input'][0]['content'])->toContain('Earlier conversation summary');
    expect($requests[3][0]['input'][1]['content'])->toBe('Tell me more');
});
