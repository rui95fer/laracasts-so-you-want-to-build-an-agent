<?php

namespace App\AI\Tools;

use App\AI\Memory\Store;
use Illuminate\Support\Facades\Http;

class Memory implements Tool
{
    public function __construct(protected Store $store = new Store) {}

    public function definition(): array
    {
        return [
            'type' => 'function',
            'name' => 'remember',
            'description' => <<<'DESCRIPTION'
Save a stable long-term fact about the user or their project. Use this tool only for information that will remain useful across future sessions. Examples: user preferences (editor, coding style), identity (name, role), project context (framework, conventions). Do not use for transient details, things already remembered, or trivial exchanges like greetings. Phrase the memory as a concise third-person statement that will make sense without prior context.
DESCRIPTION,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'fact' => [
                        'type' => 'string',
                        'description' => 'The fact to remember, phrased as a concise third-person statement.',
                    ],
                ],
                'required' => ['fact'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ];
    }

    public function use(array $arguments = []): string
    {
        $fact = $arguments['fact'] ?? '';

        if (! is_string($fact) || trim($fact) === '') {
            return 'The "fact" argument is required.';
        }

        $existingMemory = $this->store->fetch();

        $refreshedMemory = $this->refreshMemory($existingMemory, $fact);

        if (trim($refreshedMemory) === trim($existingMemory)) {
            return 'Fact already known.';
        }

        $this->store->update($refreshedMemory);

        return "Remembered: {$fact}";
    }

    protected function refreshMemory(string $existingMemory, string $fact): string
    {
        $input = "Existing memory:\n{$existingMemory}\n\nNew fact:\n{$fact}";

        $response = Http::withToken(config('services.openai.key'))
            ->post('https://api.openai.com/v1/responses', [
                'model' => config('services.openai.model', 'gpt-5.4-nano'),
                'instructions' => <<<'INSTRUCTIONS'
You maintain a small markdown scratch document of long-term facts about a user. Here is the existing markdown and a new candidate fact. Return a new fully updated markdown file that follows these rules:
- If the candidate fact is genuinely new information, add it as a bullet point.
- If it is already represented, return the existing markdown unchanged.
- Do not editorialize or add commentary.
- If the candidate contradicts an existing fact, replace the old fact with the new one.
- Keep facts as concise third-person statements.
INSTRUCTIONS,
                'input' => [
                    ['role' => 'user', 'content' => $input],
                ],
            ])
            ->throw()
            ->json();

        return $response['output'][0]['content'][0]['text'] ?? $existingMemory;
    }
}
