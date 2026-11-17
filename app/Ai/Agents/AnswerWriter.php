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
 * Answers "why this company" and "why you" for an application form, from the job and the
 * candidate's facts. Like the letter writer, it gets only the job's skills the candidate
 * has, so it has no missing skill to claim.
 */
#[Temperature(0)]
final class AnswerWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $skills  The job's skills found in the candidate's profile.
     * @param  string  $language  "English" or "German".
     */
    public function __construct(private string $job, private string $company, private array $skills, private string $language) {}

    /** The job and the facts, each marked off as data, then the language. */
    public function input(string $facts): string
    {
        $mark = function (string $value): string {
            // Until nothing changes: removing one marker can join the rest of a nested one.
            do {
                $before = $value;
                $value = (string) preg_replace('/<\/?(?:job|company|skills|facts)>/i', '', $value);
            } while ($value !== $before);

            return $value;
        };

        return '<job>'.$mark($this->job).'</job>'
            ."\n<company>".$mark($this->company).'</company>'
            ."\n<skills>".$mark($this->skills === [] ? 'none' : implode(', ', $this->skills)).'</skills>'
            ."\n<facts>\n".$mark($facts)."\n</facts>"
            ."\n\nWrite in {$this->language}.";
    }

    public function instructions(): string
    {
        return Prompts::get('application-answers');
    }

    /** The same context size as the fact checker, which checks the answers next. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'why_company' => $schema->string()->required(),
            'why_fit' => $schema->string()->required(),
        ];
    }
}
