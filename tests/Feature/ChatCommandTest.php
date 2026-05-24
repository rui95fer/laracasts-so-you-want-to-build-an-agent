<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it sends a single user message to openai responses api', function () {
    Http::fakeSequence()->push([
        'output' => [
            [
                'type' => 'message',
                'content' => [
                    ['type' => 'output_text', 'text' => 'I am doing great.'],
                ],
            ],
        ],
    ]);

    $this->artisan('chat')
        ->expectsQuestion('What is on your mind?', 'How are you today?')
        ->expectsOutput('I am doing great.')
        ->assertSuccessful();

    Http::assertSentCount(1);

    $requests = Http::recorded();

    /** @var Request $request */
    $request = $requests[0][0];

    expect($request->url())->toBe('https://api.openai.com/v1/responses');
    expect($request['instructions'])->toBe('You are a helpful assistant.');
    expect($request['input'])->toBe([
        ['role' => 'user', 'content' => 'How are you today?'],
    ]);
});
