# Laracasts - So You Want to Build an Agent

## Episode 01 — Your First AI Response

- **Prompt AI via HTTP request with your API token, endpoint, and parameters.**
  ```php
  $response = Http::withToken(config('services.openai.key'))
      ->post('https://api.openai.com/v1/responses', [
          'model' => 'gpt-5.4-nano',
          'instructions' => 'You are a helpful assistant.',
          'input' => [['role' => 'user', 'content' => 'How are you today?']],
      ])
      ->throw()
      ->json();
  ```

- **Store API keys in `.env` and access them via `config()` in your services config.**
  ```env
  OPENAI_API_KEY=your_api_key_here
  ```
  ```php
  // config/services.php
  'openai' => ['key' => env('OPENAI_API_KEY')];
  ```

- **Use Laravel Prompts `text()` to collect dynamic user input from CLI commands.**
  ```php
  $prompt = text(label: 'What is on your mind?', required: true);
  ```

- **Wrap async API calls with `spin()` to show visual feedback while waiting.**
  ```php
  $response = spin(
      callback: fn () => $this->runModel($prompt),
      message: 'Thinking about that...'
  );
  ```

- **Extract the response text from nested array: `output[0]['content'][0]['text']`.**
  ```php
  $text = $response['output'][0]['content'][0]['text'];
  $this->info($text);
  ```

- **Move HTTP logic into a separate method like `runModel()` to keep commands clean.**
  ```php
  private function runModel(string $prompt): array
  {
      return Http::withToken(config('services.openai.key'))
          ->post('https://api.openai.com/v1/responses', [
              'model' => 'gpt-5.4-nano',
              'instructions' => 'You are a helpful assistant.',
              'input' => [['role' => 'user', 'content' => $prompt]],
          ])
          ->throw()
          ->json();
  }
  ```

## Episode 02 — Have A Dialog

- **Rename your command signature to `dialogue` when the command enables ongoing conversation.**
  ```php
  protected $signature = 'dialogue';
  protected $description = 'Converse with OpenAI';
  ```

- **Maintain a history array that tracks every message (user and AI) to preserve conversation context.**
  ```php
  $history = [];
  $history[] = ['role' => 'user', 'content' => $prompt];
  ```

- **Send the full history array on every API call so the model can reference previous messages.**
  ```php
  $response = Http::withToken(config('services.openai.key'))
      ->post('https://api.openai.com/v1/responses', [
          'model' => 'gpt-5.4-nano',
          'input' => $history,
      ])
      ->throw()
      ->json();
  ```

- **Append the AI's response to history immediately after receiving it so the next prompt includes it.**
  ```php
  $history = [...$history, ...$response['output']];
  ```

- **Wrap the entire flow in `while (true)` to keep prompting, sending history, and collecting responses indefinitely.**
  ```php
  while (true) {
      $prompt = text(label: 'What is on your mind?', required: true);
      $history[] = ['role' => 'user', 'content' => $prompt];
      $response = spin(fn () => $this->runModel($history), 'Thinking...');
      $history = [...$history, ...$response['output']];
      $this->info($response['output'][0]['content'][0]['text']);
  }
  ```

## Episode 03 — The Agent Loop

- **Combine a loop with tools to give AI the ability to request capabilities it needs.**
  ```text
  Outer loop: User prompt → Model response → User sees result
  Inner loop: Model asks for tool → App runs tool → Model asks for more or responds
  ```

- **Declare tools in each API request so the model knows what functions it can request.**
  ```php
  'tools' => [
      [
          'type' => 'function',
          'name' => 'get_current_time',
          'description' => 'Get current server time as ISO string.',
          'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
      ],
  ],
  ```

- **Check if the first output item is a tool call instead of text by filtering the response.**
  ```php
  $functionCalls = collect($response['output'])
      ->filter(fn (array $item): bool => $item['type'] === 'function_call');
  ```

- **Return tool results to the model as `function_call_output` with the matching `call_id` so it knows which result belongs to which request.**
  ```php
  $history[] = [
      'type' => 'function_call_output',
      'call_id' => $call['call_id'],
      'output' => now()->toIso8601String(),
  ];
  ```

- **Wrap the model call in an inner loop that runs until there are no more tool calls, then break when ready to provide text response.**
  ```php
  while (true) {
      $response = spin(fn () => $this->runModel($history), 'Thinking...');
      $functionCalls = collect($response['output'])
          ->filter(fn (array $item): bool => $item['type'] === 'function_call');
      
      if ($functionCalls->isEmpty()) {
          break;
      }
      
      foreach ($functionCalls as $call) {
          $output = $this->runTool($call['name'], $call['arguments']);
          $history[] = [
              'type' => 'function_call_output',
              'call_id' => $call['call_id'],
              'output' => (string) $output,
          ];
      }
  }
  ```

- **Loop over all function calls to run each tool, since the model can request multiple tools in a single response.**
  ```php
  foreach ($functionCalls as $call) {
      match ($call['name']) {
          'get_current_time' => $history[] = [
              'type' => 'function_call_output',
              'call_id' => $call['call_id'],
              'output' => now()->toIso8601String(),
          ],
          'read_file' => $history[] = [
              'type' => 'function_call_output',
              'call_id' => $call['call_id'],
              'output' => file_get_contents(base_path(json_decode($call['arguments'], true)['path'])),
          ],
      };
  }
  ```

## Episode 04 — Extract A Tool Class

- **Create separate tool classes instead of inline conditionals to keep agent code clean and follow SOLID principles.**
  ```php
  // app/AI/Tools/CurrentTime.php
  // app/AI/Tools/ReadFile.php
  ```

- **Use a `Tool` interface so every tool implements the same contract with `definition()` and `use()` methods.**
  ```php
  interface Tool
  {
      public function definition(): array;
      public function use(array $arguments = []): mixed;
  }
  ```

- **Keep API schema in `definition()` so the model knows what the tool is and what arguments it accepts.**
  ```php
  class CurrentTime implements Tool
  {
      public function definition(): array
      {
          return [
              'type' => 'function',
              'name' => 'get_current_time',
              'description' => 'Get current server time as ISO string.',
              'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
          ];
      }

      public function use(array $arguments = []): string
      {
          return now()->toIso8601String();
      }
  }
  ```

- **Extract a `tools()` method that returns an array of tool instances so you can loop through them instead of using conditionals.**
  ```php
  private function tools(): array
  {
      return [new CurrentTime(), new ReadFile()];
  }
  ```

- **Map tool definitions from instances before sending to the API so it receives only the schema, not PHP objects.**
  ```php
  $toolDefinitions = collect($this->tools())
      ->map(fn (Tool $tool): array => $tool->definition())
      ->values()
      ->all();
  
  // Send $toolDefinitions in API request
  ```

- **Loop through tools to find the matching name and call its `use()` method with decoded arguments.**
  ```php
  foreach ($this->tools() as $tool) {
      if ($tool->definition()['name'] === $call['name']) {
          $output = $tool->use(json_decode($call['arguments'], true));
          $history[] = [
              'type' => 'function_call_output',
              'call_id' => $call['call_id'],
              'output' => (string) $output,
          ];
          break;
      }
  }
  ```

## Episode 05 — Make a Revenue Tool

- **Create domain-specific tools to give the AI access to business data it otherwise couldn't query.**
  ```php
  class Revenue implements Tool
  {
      public function definition(): array
      {
          return [
              'type' => 'function',
              'name' => 'site_revenue',
              'description' => 'Get site revenue for a specific period.',
              'parameters' => [...],
          ];
      }

      public function use(array $arguments = []): string { ... }
  }
  ```

- **Define tool parameters with JSON schema enum so the AI chooses from valid options (daily, monthly, quarterly, yearly).**
  ```php
  'parameters' => [
      'type' => 'object',
      'properties' => [
          'period' => [
              'type' => 'string',
              'enum' => ['daily', 'monthly', 'quarterly', 'yearly'],
              'description' => 'The period of time to fetch revenue for.',
          ],
      ],
      'required' => ['period'],
      'additionalProperties' => false,
  ],
  ```

- **Enable `strict` mode in your tool definition to enforce structured output and prevent invalid parameter values.**
  ```php
  'parameters' => [
      'type' => 'object',
      'properties' => ['period' => ['type' => 'string', 'enum' => [...]]],
      'required' => ['period'],
      'additionalProperties' => false,
      'strict' => true,
  ],
  ```

- **Stub tool responses with hardcoded match statements before writing database queries so you can test the agent loop.**
  ```php
  public function use(array $arguments = []): string
  {
      return match ($arguments['period'] ?? null) {
          'daily' => '900',
          'monthly' => '18000',
          'quarterly' => '120000',
          'yearly' => '850000',
          default => '0',
      };
  }
  ```

- **Always cast tool output to string when appending `function_call_output` to prevent type errors in the API request.**
  ```php
  $history[] = [
      'type' => 'function_call_output',
      'call_id' => $call['call_id'],
      'output' => (string) $tool->use(json_decode($call['arguments'], true)),
  ];
  ```

## Episode 06 — Structured Output

- **Define a JSON schema in the API request to enforce a specific output structure when you need deterministic, parseable responses.**
  ```php
  'text' => [
      'format' => [
          'type' => 'json_schema',
          'name' => 'agent_response',
          'schema' => [
              'type' => 'object',
              'properties' => ['response' => ['type' => 'string']],
              'required' => ['response'],
              'additionalProperties' => false,
          ],
      ],
  ],
  ```

- **Enable `strict: true` in the schema to guarantee the AI's response matches your structure exactly, preventing hallucinations and format violations.**
  ```php
  'schema' => [
      'type' => 'object',
      'properties' => ['nouns' => [...], 'adjectives' => [...], 'verbs' => [...]],
      'required' => ['nouns', 'adjectives', 'verbs'],
      'additionalProperties' => false,
      'strict' => true,
  ],
  ```

- **Extract agent logic into an abstract `Agent` base class so each agent can define its own instructions, tools, and schema.**
  ```php
  abstract class Agent
  {
      public array $history = [];
      
      protected function instructions(): string
      {
          return 'You are a helpful assistant.';
      }
      
      protected function tools(): array
      {
          return [];
      }
      
      protected function schema(): ?array
      {
          return null;
      }
  }
  ```

- **Create specialized agent subclasses (ChatbotAgent, GrammarAgent) so different parts of your app can use agents designed for specific purposes.**
  ```php
  class ChatbotAgent extends Agent
  {
      protected function instructions(): string
      {
          return 'You are a sarcastic but helpful coding assistant.';
      }
      
      protected function tools(): array
      {
          return [new CurrentTime(), new ReadFile()];
      }
  }
  
  class GrammarAgent extends Agent
  {
      protected function schema(): ?array
      {
          return [
              'type' => 'object',
              'properties' => [
                  'nouns' => ['type' => 'array', 'items' => ['type' => 'string']],
                  'adjectives' => ['type' => 'array', 'items' => ['type' => 'string']],
                  'verbs' => ['type' => 'array', 'items' => ['type' => 'string']],
              ],
              'required' => ['nouns', 'adjectives', 'verbs'],
              'additionalProperties' => false,
              'strict' => true,
          ];
      }
  }
  ```

- **Parse structured responses as PHP arrays to interact with AI output programmatically in your codebase.**
  ```php
  $response = $agent->prompt('The brown dog jumped over a fence.');
  $data = json_decode($response, true);
  
  foreach ($data['nouns'] as $noun) {
      // Use extracted data in your app
  }
  ```

## Episode 07 — Filesystem Tools

- **Give the agent core coding abilities (run scripts, read/write files, list directories, glob paths, search content) so it can do meaningful project work.**
  ```text
  Tools to add: read_file, write_file, run_bash_script, list_files, glob_files, search_in_files
  ```

- **Wrap common capabilities in dedicated tools instead of relying only on generic shell commands to keep behavior deterministic and easier to trace.**
  ```php
  interface Tool
  {
      public function definition(): array;
      public function use(array $arguments = []): mixed;
  }
  ```

- **Use each tool filename as the contract for what it should do, then implement all tool classes to match that responsibility.**
  ```php
  // app/AI/Tools/ReadFile.php
  // app/AI/Tools/WriteFile.php
  // app/AI/Tools/RunBashScript.php
  // app/AI/Tools/ListFiles.php
  // app/AI/Tools/GlobFiles.php
  // app/AI/Tools/SearchInFiles.php
  ```

- **Validate inputs and return clear errors in filesystem tools so the agent can recover from bad paths safely.**
  ```php
  $path = base_path($arguments['path'] ?? '');

  if (! file_exists($path)) {
      return 'Error: File does not exist.';
  }

  return file_get_contents($path);
  ```

- **Run shell commands through a process tool and return either stdout or stderr so the agent can inspect test/build results.**
  ```php
  $process = Process::run($arguments['command'] ?? '');

  if (! $process->successful()) {
      return $process->errorOutput();
  }

  return $process->output();
  ```

- **Register all new tools in your chatbot agent so tool calls can be selected during the inner agent loop.**
  ```php
  protected function tools(): array
  {
      return [
          new ReadFile(),
          new WriteFile(),
          new RunBashScript(),
          new ListFiles(),
          new GlobFiles(),
          new SearchInFiles(),
      ];
  }
  ```

- **Use the command loop to repeatedly prompt the agent and verify real edits, like creating files or adding routes, are done through tool calls.**
  ```php
  while (true) {
      $prompt = text(label: 'What is on your mind?', required: true);
      $this->line($agent->prompt($prompt));
  }
  ```

- **Log tool call names during development when you need observability into what the agent is doing behind the scenes.**
  ```php
  $this->line("Running tool call: {$call['name']}");
  ```

- **The main pattern remains a loop within a loop: the model decides, tools execute, results feed back, then the agent responds.**
   ```text
   Outer loop: user prompt -> final assistant response
   Inner loop: assistant tool call -> tool result -> assistant next action
   ```

## Episode 08 — Compacting

- **Long conversations blow out context tokens as history array grows indefinitely, so you need compaction to summarize old messages.**
   ```text
   Without compaction: user message → tool result → assistant response → user message...
   After 20-30 exchanges, history array becomes too large for API token limits.
   ```

- **Base compaction on message count (simplest) rather than token usage for rapid implementation, though tokens would be more accurate.**
   ```php
   // Simple: Compact when history count reaches threshold
   // Advanced: Compact when token usage exceeds a limit
   // We'll use message count for this example.
   ```

- **Use a PHP attribute at class level to declare compaction thresholds, keeping configuration as metadata near the class definition.**
   ```php
   #[CompactsAfter(3)]
   class ChatbotAgent extends Agent
   {
       // This agent compacts its history when it reaches 3 messages
   }
   ```

- **Create a PHP attribute class with a threshold property and target the class level using `Attribute` constraint.**
   ```php
   #[Attribute(Attribute::TARGET_CLASS)]
   class CompactsAfter
   {
       public function __construct(public int $threshold) {}
   }
   ```

- **Read attributes using Reflection API to extract compaction configuration from the agent class at runtime.**
   ```php
   $reflection = new ReflectionClass($this);
   $attributes = $reflection->getAttributes(CompactsAfter::class);
   $config = $attributes[0]?->newInstance();
   $threshold = $config?->threshold ?? 30; // Default if not specified
   ```

- **Check if history exceeds the threshold before prompting, and if so, call a compaction method that summarizes old messages.**
   ```php
   private function maybeCompact(): void
   {
       if (count($this->history) >= $this->getThreshold()) {
           $this->compact();
       }
   }
   ```

- **Summarize old conversation history by sending all messages to the AI with instructions to preserve key facts and decisions.**
   ```php
   $response = Http::withToken(config('services.openai.key'))
       ->post('https://api.openai.com/v1/responses', [
           'model' => 'gpt-5.4-nano',
           'instructions' => 'Summarize the following conversation history concisely, preserve the key facts and decisions, tool results, unresolved questions, omit pleasantries and redundant exchanges.',
           'input' => $this->history,
       ])
       ->throw()
       ->json();
   ```

- **Replace history with the summary wrapped in a user message to maintain conversation context, prefixing with "Earlier conversation summary:" for clarity.**
   ```php
   $summary = $response['output'][0]['content'][0]['text'];
   $this->history = [
       ['role' => 'user', 'content' => "Earlier conversation summary:\n{$summary}"],
   ];
   ```

- **Run compaction before each prompt so you reset history only when needed, then continue the agent loop with the summarized context.**
   ```php
   public function prompt(string $input): string
   {
       $this->maybeCompact(); // Reset history if threshold exceeded
       $this->history[] = ['role' => 'user', 'content' => $input];
       // ... run model, handle tools, return response
   }
   ```


