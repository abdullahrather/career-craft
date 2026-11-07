<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/** A one-sentence answer, so the admin can see that the chosen provider works. */
final class ProviderCheck implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return Prompts::get('provider-check');
    }
}
