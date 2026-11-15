<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Prompts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Lists the claims in an AI suggestion that the candidate's own facts don't support, such
 * as a new employer, degree or result. FactGuard checks its answer before using it.
 */
#[Temperature(0)]
final class FactChecker implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** The kinds of claim it reports. */
    public const array KINDS = ['employer', 'school', 'degree', 'certificate', 'title', 'skill', 'number', 'date', 'result', 'other'];

    public function instructions(): string
    {
        return Prompts::get('fact-check');
    }

    /** Room for a whole profile; Ollama's default context is too small. */
    public function providerOptions(Lab|string $provider): array
    {
        return ['num_ctx' => 8192];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'claims' => $schema->array()->items($schema->object([
                'quote' => $schema->string()->required(),
                'kind' => $schema->string()->enum(self::KINDS)->required(),
            ]))->required(),
        ];
    }

    /** The facts and the suggestion as the AI gets them, each marked off as data. */
    public static function input(string $facts, string $suggestion): string
    {
        $strip = fn (string $text): string => str_replace(['<facts>', '</facts>', '<suggestion>', '</suggestion>'], '', $text);

        return "<facts>\n{$strip($facts)}\n</facts>\n\n<suggestion>{$strip($suggestion)}</suggestion>";
    }
}
