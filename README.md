# So You Want to Build an Agent - Course Notes

A comprehensive guide to building AI agents with Laravel, based on the Laracasts course.

## Course Overview

This course teaches you how to build AI agents step-by-step, starting with the fundamentals of prompting AI and receiving responses, through to building complex agents that can perform tasks autonomously.

---

## Episode 01: Your First AI Response

### Key Lessons & Practical Examples

- **AI interaction is just a simple HTTP request with the right token and parameters**
  ```bash
  # Basic pattern:
  Http::withToken(config('services.openai.key'))
      ->post('https://api.openai.com/v1/chat/completions', [
          'model' => 'gpt-4-turbo-mini',
          'messages' => [...]
      ])
      ->throw()
      ->json();
  ```

- **Store API keys securely in .env and access them via config()**
  ```env
  OPENAI_API_KEY=your_api_key_here
  ```
  ```php
  // In config/services.php
  'openai' => [
      'key' => env('OPENAI_API_KEY'),
  ],
  
  // Access anywhere
  config('services.openai.key')
  ```

- **Create an Artisan command to interact with AI**
  ```bash
  php artisan make:command ChatCommand
  ```
  ```php
  // In app/Console/Commands/ChatCommand.php
  class ChatCommand extends Command
  {
      protected $signature = 'chat';
      protected $description = 'Chat with an AI model';
      
      public function handle()
      {
          $prompt = text(label: 'What is on your mind?', required: true);
          $response = $this->runModel($prompt);
          $this->info($response);
      }
  }
  ```

- **Use Laravel Prompts for elegant CLI input/output**
  ```bash
  composer require laravel/prompts
  ```
  ```php
  use function Laravel\Prompts\text;
  
  $prompt = text(
      label: 'What is on your mind?',
      placeholder: 'Enter your question...',
      required: true,
  );
  ```

- **Provide user feedback while waiting for AI responses with spin()**
  ```php
  use function Laravel\Prompts\spin;
  
  $response = spin(
      callback: fn() => $this->runModel($prompt),
      message: 'Thinking about that...'
  );
  ```

- **Understand OpenAI response structure before extracting data**
  ```php
  // Response path: response['choices'][0]['message']['content']
  $response = Http::withToken(config('services.openai.key'))
      ->post('https://api.openai.com/v1/chat/completions', [
          'model' => 'gpt-4-turbo-mini',
          'messages' => [
              ['role' => 'system', 'content' => 'You are a helpful assistant.'],
              ['role' => 'user', 'content' => $prompt]
          ]
      ])
      ->throw()
      ->json();
  
  $textContent = $response['choices'][0]['message']['content'];
  ```

- **Pass system instructions to guide the AI's behavior**
  ```php
  'messages' => [
      [
          'role' => 'system',
          'content' => 'You are a helpful assistant. Format all responses for a first grader to understand.'
      ],
      ['role' => 'user', 'content' => $prompt]
  ]
  ```

- **Be aware that responses can contain tool calls, not just text**
  - The first item in output could be a tool call instead of text
  - Later episodes will handle this properly
  - For now, naively assume the first item is always text

- **Complete working example: Basic chat command**
  ```php
  <?php
  
  namespace App\Console\Commands;
  
  use Illuminate\Console\Command;
  use Illuminate\Support\Facades\Http;
  use function Laravel\Prompts\text;
  use function Laravel\Prompts\spin;
  
  class ChatCommand extends Command
  {
      protected $signature = 'chat';
      protected $description = 'Chat with an AI model';
  
      public function handle()
      {
          $prompt = text(label: 'What is on your mind?', required: true);
          
          $response = spin(
              callback: fn() => $this->runModel($prompt),
              message: 'Thinking about that...'
          );
          
          $this->info($response);
      }
  
      private function runModel(string $prompt): string
      {
          $response = Http::withToken(config('services.openai.key'))
              ->post('https://api.openai.com/v1/chat/completions', [
                  'model' => 'gpt-4-turbo-mini',
                  'messages' => [
                      ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                      ['role' => 'user', 'content' => $prompt]
                  ]
              ])
              ->throw()
              ->json();
          
          return $response['choices'][0]['message']['content'];
      }
  }
  ```
  
  Usage:
  ```bash
  php artisan chat
  # What is on your mind? What is 2 + 2?
  # Thinking about that...
  # The answer is 4.
  ```

---

## Episode 02: Have A Dialog

### Key Lessons & Practical Examples

- **Rename the command from `chat` to `dialogue` to reflect ongoing conversation**
  ```php
  // Before
  protected $signature = 'chat';
  protected $description = 'Chat with an AI model';

  // After
  protected $signature = 'dialogue';
  protected $description = 'Converse with OpenAI';
  ```

- **Track conversation history as the source of truth**
  ```php
  /** @var array<int, array{role: string, content: string}> $history */
  $history = [];
  ```

- **Append each user prompt to history before calling the model**
  ```php
  $prompt = text(label: 'What is on your mind?', required: true);

  $history[] = [
      'role' => 'user',
      'content' => $prompt,
  ];
  ```

- **Send full history as input so the model keeps context**
  ```php
  $response = $this->runModel($history);
  ```
  ```php
  private function runModel(array $input): array
  {
      return Http::withToken(config('services.openai.key'))
          ->post('https://api.openai.com/v1/responses', [
              'model' => 'gpt-5.4-nano',
              'input' => $input,
          ])
          ->throw()
          ->json();
  }
  ```

- **Append AI output back into history to continue the dialog**
  ```php
  $history = [
      ...$history,
      ...$response['output'],
  ];
  ```

- **Use a `while (true)` loop for continuous back-and-forth**
  ```php
  while (true) {
      $prompt = text(label: 'What is on your mind?', required: true);

      $history[] = [
          'role' => 'user',
          'content' => $prompt,
      ];

      $response = spin(
          callback: fn() => $this->runModel($history),
          message: 'Thinking about that...'
      );

      $history = [
          ...$history,
          ...$response['output'],
      ];

      $this->info($response['output'][0]['content'][0]['text'] ?? 'No text response returned.');
  }
  ```

- **Without history, every prompt is a clean slate; with history, memory works**
  ```text
  User: Hi there
  AI: Hi! How can I help you today?
  User: Call me Jeffrey
  AI: Sure thing, Jeffrey.
  User: What is my name?
  AI: Your name is Jeffrey.
  ```

- **The loop is necessary, but tools are what make it a true agent**
  ```text
  Episode 01: Single prompt -> single response
  Episode 02: Loop + history -> ongoing dialog
  Episode 03+: Add tool execution -> agent behavior
  ```

---

## Episode 03: The Agent Loop

### Key Lessons & Practical Examples

- **An agent needs two ingredients: a loop and tools**
  ```text
  Loop   -> lets the model think in steps
  Tools  -> let the model gather real information

  Example flow:
  User asks a question
  -> AI requests a tool
  -> App runs the tool
  -> AI receives the tool result
  -> AI answers the user
  ```

- **Tools are callable functions that extend the AI with real capabilities**
  ```php
  'tools' => [
      [
          'type' => 'function',
          'name' => 'get_current_time',
          'description' => 'Get the current server time as an ISO string.',
          'parameters' => [
              'type' => 'object',
              'properties' => [],
              'required' => [],
          ],
      ],
  ]
  ```

- **The Responses API is stateless, so available tools must be sent with every request**
  ```php
  private function runModel(array $history): array
  {
      return Http::withToken(config('services.openai.key'))
          ->post('https://api.openai.com/v1/responses', [
              'model' => 'gpt-5.4-nano',
              'input' => $history,
              'tools' => [
                  [
                      'type' => 'function',
                      'name' => 'get_current_time',
                      'description' => 'Get the current server time as an ISO string.',
                      'parameters' => [
                          'type' => 'object',
                          'properties' => [],
                          'required' => [],
                      ],
                  ],
              ],
          ])
          ->throw()
          ->json();
  }
  ```

- **Once tools are enabled, the first output item may be a function call instead of text**
  ```php
  $call = $response['output'][0];

  if ($call['type'] === 'function_call') {
      // The model is asking the app to run a tool.
      $toolName = $call['name'];
  }
  ```

- **The app must execute the requested tool and append the tool result back into history**
  ```php
  if ($call['name'] === 'get_current_time') {
      $history[] = [
          'type' => 'function_call_output',
          'call_id' => $call['call_id'],
          'output' => now()->toIso8601String(),
      ];
  }
  ```

- **Use an inner loop for the agent's private reasoning and an outer loop for user dialogue**
  ```php
  while (true) {
      $prompt = text(label: 'What is on your mind?', required: true);

      $history[] = [
          'role' => 'user',
          'content' => $prompt,
      ];

      while (true) {
          $response = spin(
              callback: fn() => $this->runModel($history),
              message: 'Thinking about that...'
          );

          $history = [
              ...$history,
              ...$response['output'],
          ];

          $functionCalls = collect($response['output'])
              ->filter(fn(array $item): bool => $item['type'] === 'function_call');

          if ($functionCalls->isEmpty()) {
              $this->info($response['output'][0]['content'][0]['text'] ?? 'No text response returned.');
              break;
          }

          foreach ($functionCalls as $call) {
              if ($call['name'] === 'get_current_time') {
                  $history[] = [
                      'type' => 'function_call_output',
                      'call_id' => $call['call_id'],
                      'output' => now()->toIso8601String(),
                  ];
              }
          }
      }
  }
  ```

- **A single model response can request multiple tools, so collect and process all function calls**
  ```php
  $functionCalls = collect($response['output'])
      ->filter(fn(array $item): bool => $item['type'] === 'function_call');

  foreach ($functionCalls as $call) {
      // Run each requested tool before asking the model again.
  }
  ```

- **Tools can accept structured arguments, like a file-reading tool**
  ```php
  [
      'type' => 'function',
      'name' => 'read_file',
      'description' => 'Read a file from the project root.',
      'parameters' => [
          'type' => 'object',
          'properties' => [
              'path' => [
                  'type' => 'string',
                  'description' => 'The relative file path to read.',
              ],
          ],
          'required' => ['path'],
          'additionalProperties' => false,
      ],
      'strict' => true,
  ]
  ```

- **Tool arguments arrive as JSON, so decode them before using them**
  ```php
  $arguments = json_decode($call['arguments']);

  $contents = file_get_contents(
      base_path($arguments->path)
  );
  ```

- **`call_id` links each tool result to the exact function call that requested it**
  ```php
  $history[] = [
      'type' => 'function_call_output',
      'call_id' => $call['call_id'],
      'output' => $contents,
  ];
  ```

- **Show which tool is running so the CLI feels alive while the agent works**
  ```php
  info("Running tool: {$call['name']}(...) ");
  ```

- **A practical multi-tool example is reading both `package.json` and `composer.json` in one turn**
  ```text
  User: Read the contents of package.json and composer.json.

  AI response #1:
  - function_call: read_file({"path":"package.json"})
  - function_call: read_file({"path":"composer.json"})

  App:
  - reads package.json
  - reads composer.json
  - appends both function_call_output items to history

  AI response #2:
  "Here is what each file contains..."
  ```

- **A minimal agent command combines history, tools, an outer dialogue loop, and an inner tool loop**
  ```php
  <?php

  namespace App\Console\Commands;

  use Illuminate\Console\Command;
  use Illuminate\Support\Collection;
  use Illuminate\Support\Facades\Http;
  use function Laravel\Prompts\info;
  use function Laravel\Prompts\spin;
  use function Laravel\Prompts\text;

  class AgentCommand extends Command
  {
      protected $signature = 'agent';
      protected $description = 'Run a simple tool-using agent';

      public function handle(): void
      {
          $history = [];

          while (true) {
              $prompt = text(label: 'What is on your mind?', required: true);

              $history[] = [
                  'role' => 'user',
                  'content' => $prompt,
              ];

              while (true) {
                  $response = spin(
                      callback: fn() => $this->runModel($history),
                      message: 'Thinking about that...'
                  );

                  $history = [
                      ...$history,
                      ...$response['output'],
                  ];

                  $functionCalls = collect($response['output'])
                      ->filter(fn(array $item): bool => $item['type'] === 'function_call');

                  if ($functionCalls->isEmpty()) {
                      $this->info($response['output'][0]['content'][0]['text'] ?? 'No text response returned.');
                      break;
                  }

                  $this->runTools($functionCalls, $history);
              }
          }
      }

      private function runModel(array $history): array
      {
          return Http::withToken(config('services.openai.key'))
              ->post('https://api.openai.com/v1/responses', [
                  'model' => 'gpt-5.4-nano',
                  'input' => $history,
                  'tools' => [
                      [
                          'type' => 'function',
                          'name' => 'get_current_time',
                          'description' => 'Get the current server time as an ISO string.',
                          'parameters' => [
                              'type' => 'object',
                              'properties' => [],
                              'required' => [],
                          ],
                      ],
                      [
                          'type' => 'function',
                          'name' => 'read_file',
                          'description' => 'Read a file from the project root.',
                          'parameters' => [
                              'type' => 'object',
                              'properties' => [
                                  'path' => [
                                      'type' => 'string',
                                      'description' => 'The relative file path to read.',
                                  ],
                              ],
                              'required' => ['path'],
                              'additionalProperties' => false,
                          ],
                          'strict' => true,
                      ],
                  ],
              ])
              ->throw()
              ->json();
      }

      private function runTools(Collection $functionCalls, array &$history): void
      {
          foreach ($functionCalls as $call) {
              info("Running tool: {$call['name']}(...) ");

              if ($call['name'] === 'get_current_time') {
                  $history[] = [
                      'type' => 'function_call_output',
                      'call_id' => $call['call_id'],
                      'output' => now()->toIso8601String(),
                  ];
              }

              if ($call['name'] === 'read_file') {
                  $arguments = json_decode($call['arguments']);

                  $history[] = [
                      'type' => 'function_call_output',
                      'call_id' => $call['call_id'],
                      'output' => file_get_contents(base_path($arguments->path)),
                  ];
              }
          }
      }
  }
  ```




