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
 * Rewords one piece of a CV for a job: the summary or one bullet point. It gets the job's
 * title and only the job's skills the candidate already has, so it can bring those
 * forward but has no missing skill to slip in. One text per request, as with the Writer.
 */
#[Temperature(0)]
final class Tailor implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $skills  The job's skills found in the candidate's profile.
     * @param  string  $language  The CV's language, in English: "English" or "German".
     */
    public function __construct(private string $job, private array $skills, private string $language) {}

    /** The job and the text, each marked off so it reads as data. */
    public function input(string $text): string
    {
        $mark = fn (string $value): string => str_replace(['<job>', '</job>', '<skills>', '</skills>', '<text>', '</text>'], '', $value);
        $skills = $this->skills === [] ? 'none' : implode(', ', $this->skills);

        return '<job>'.$mark($this->job).'</job>'
            ."\n<skills>".$mark($skills).'</skills>'
            ."\n<text>".$mark($text).'</text>'
            ."\n\nAnswer in {$this->language}.";
    }

    public function instructions(): string
    {
        return Prompts::get('tailor');
    }

    /** The same context size as the Writer and the fact checker, so Ollama doesn't reload the model. */
    public function providerOptions(Lab|string $provider): array
    {
        return ['num_ctx' => 8192];
    }

    public function schema(JsonSchema $schema): array
    {
        return ['text' => $schema->string()->required()];
    }
}
