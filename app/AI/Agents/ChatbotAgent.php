<?php

namespace App\AI\Agents;

use App\AI\Attributes\CompactsAfter;
use App\AI\Tools\CurrentTime;
use App\AI\Tools\GlobFiles;
use App\AI\Tools\ListFiles;
use App\AI\Tools\ReadFile;
use App\AI\Tools\Revenue;
use App\AI\Tools\RunBashScript;
use App\AI\Tools\SearchInFiles;
use App\AI\Tools\Tool;
use App\AI\Tools\WriteFile;

#[CompactsAfter(30)]
class ChatbotAgent extends Agent
{
    /**
     * @return list<Tool>
     */
    protected function tools(): array
    {
        return [
            new CurrentTime,
            new ReadFile,
            new Revenue,
            new WriteFile,
            new RunBashScript,
            new ListFiles,
            new GlobFiles,
            new SearchInFiles,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'response' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['response'],
            'additionalProperties' => false,
        ];
    }
}
