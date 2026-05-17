<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function grammarPayload(bool $hasErrors, string $correctedText, array $corrections = []): string
{
    return json_encode([
        'has_errors' => $hasErrors,
        'corrected_text' => $correctedText,
        'corrections' => $corrections,
    ], JSON_THROW_ON_ERROR);
}

test('it reports no errors when the text is correct', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => grammarPayload(false, 'She went to the store.', [])],
                ],
            ],
        ],
    ]);

    $this->artisan('grammar')
        ->expectsQuestion('Enter text to check', 'She went to the store.')
        ->expectsOutput('✓ No grammar errors found!')
        ->expectsQuestion('Enter text to check', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

test('it displays corrected text and a corrections table', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    [
                        'type' => 'output_text',
                        'text' => grammarPayload(true, 'She went to the store.', [
                            [
                                'original' => 'goed',
                                'corrected' => 'went',
                                'explanation' => '"Goed" is not a word; past tense of "go" is "went".',
                            ],
                        ]),
                    ],
                ],
            ],
        ],
    ]);

    $this->artisan('grammar')
        ->expectsQuestion('Enter text to check', 'She goed to the store.')
        ->expectsOutput('Corrected text:')
        ->expectsOutput('She went to the store.')
        ->expectsQuestion('Enter text to check', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

test('it sends the system prompt and json_schema format in the request', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => grammarPayload(false, 'Hello world.', [])],
                ],
            ],
        ],
    ]);

    $this->artisan('grammar')
        ->expectsQuestion('Enter text to check', 'Hello world.')
        ->expectsQuestion('Enter text to check', 'exit')
        ->assertSuccessful();

    Http::assertSentCount(1);

    $requests = Http::recorded();

    /** @var Request $request */
    $request = $requests[0][0];

    expect($request['input'])->toBe([
        ['role' => 'user', 'content' => 'Hello world.'],
    ]);

    expect($request['text']['format']['type'])->toBe('json_schema');
    expect($request['text']['format']['name'])->toBe('grammar_check');
    expect($request['text']['format']['strict'])->toBeTrue();

    expect($request)->not->toHaveKey('tools');
});

test('it exits immediately without calling the model', function () {
    Http::fake();

    $this->artisan('grammar')
        ->expectsQuestion('Enter text to check', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertNothingSent();
});
