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

/** Suggests a LinkedIn headline and About section from everything the profile states. */
#[Temperature(0)]
final class LinkedInWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** @param  string  $language  "English" or "German". */
    public function __construct(private string $language) {}

    /** The facts, marked off as data, then the language to write in. */
    public function input(string $facts): string
    {
        // Until nothing changes: removing one marker can join the rest of a nested one.
        do {
            $before = $facts;
            $facts = (string) preg_replace('/<\/?facts>/i', '', $facts);
        } while ($facts !== $before);

        return "<facts>\n{$facts}\n</facts>\n\nWrite in {$this->language}.";
    }

    public function instructions(): string
    {
        return Prompts::get('linkedin');
    }

    /** The same context size as the fact checker, which checks the suggestions next. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'headline' => $schema->string()->required(),
            'about' => $schema->string()->required(),
        ];
    }
}
