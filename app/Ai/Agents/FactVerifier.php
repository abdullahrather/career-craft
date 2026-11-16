<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Takes a second look at one claim the fact checker listed: are there facts that say the
 * same? A small model lists honest rewordings as new claims; this narrower question
 * clears most of them. FactGuard only trusts a "yes" that quotes the facts exactly.
 */
#[Temperature(0)]
final class FactVerifier implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return Prompts::get('fact-verify');
    }

    /** Room for a whole profile; Ollama's default context is too small. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        // The reason comes first: a small model judges better after saying why.
        return [
            'fact' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
            'backed' => $schema->boolean()->required(),
        ];
    }

    public static function input(string $facts, string $claim, string $suggestion): string
    {
        $strip = fn (string $text): string => str_replace(['<facts>', '</facts>', '<claim>', '</claim>', '<suggestion>', '</suggestion>'], '', $text);

        return "<facts>\n{$strip($facts)}\n</facts>\n\n<claim>{$strip($claim)}</claim>\n\n<suggestion>{$strip($suggestion)}</suggestion>";
    }
}
