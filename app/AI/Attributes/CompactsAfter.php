<?php

namespace App\AI\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class CompactsAfter
{
    public function __construct(public readonly int $threshold) {}
}
