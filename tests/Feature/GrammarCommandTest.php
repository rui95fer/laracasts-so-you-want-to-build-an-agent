<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function grammarPayload(array $nouns, array $adjectives, array $verbs): string
{
    return json_encode([
        'nouns' => $nouns,
        'adjectives' => $adjectives,
        'verbs' => $verbs,
    ], JSON_THROW_ON_ERROR);
}

test('it outputs structured grammar extraction json', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => grammarPayload(['fox', 'dog'], ['quick', 'brown', 'lazy'], ['jumps'])],
                ],
            ],
        ],
    ]);

    $this->artisan('grammar')
        ->expectsQuestion('Enter sentence', 'The quick brown fox jumps over the lazy dog.')
        ->expectsQuestion('Enter sentence', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertSentCount(1);
});

test('it sends instructions and a strict json schema format in the request', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => grammarPayload(['world'], [], ['hello'])],
                ],
            ],
        ],
    ]);

    $this->artisan('grammar')
        ->expectsQuestion('Enter sentence', 'Hello world')
        ->expectsQuestion('Enter sentence', 'exit')
        ->assertSuccessful();

    Http::assertSentCount(1);

    $requests = Http::recorded();

    /** @var Request $request */
    $request = $requests[0][0];

    expect($request['input'])->toBe([
        ['role' => 'user', 'content' => 'Hello world'],
    ]);

    expect($request['instructions'])->toContain('Extract nouns, adjectives, and verbs');
    expect($request['text']['format']['type'])->toBe('json_schema');
    expect($request['text']['format']['strict'])->toBeTrue();
    expect($request['text']['format']['schema']['required'])->toBe(['nouns', 'adjectives', 'verbs']);
    expect($request['tools'])->toBe([]);
});

test('it exits immediately without calling the model', function () {
    Http::fake();

    $this->artisan('grammar')
        ->expectsQuestion('Enter sentence', 'exit')
        ->expectsOutput('Goodbye!')
        ->assertSuccessful();

    Http::assertNothingSent();
});
