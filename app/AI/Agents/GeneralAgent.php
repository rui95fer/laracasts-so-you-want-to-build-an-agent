<?php

namespace App\AI\Agents;

use App\AI\Agent;
use App\AI\Tools\CurrentTime;
use App\AI\Tools\ReadFile;
use App\AI\Tools\Revenue;

class GeneralAgent extends Agent
{
    protected function tools(): array
    {
        return [
            new CurrentTime,
            new ReadFile,
            new Revenue,
        ];
    }
}
