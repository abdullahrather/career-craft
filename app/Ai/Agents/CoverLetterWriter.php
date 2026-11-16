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
 * Writes the subject and body of a cover letter from the job and the candidate's facts.
 * It gets only the job's skills the candidate has, never the post's full text, so it has
 * no missing skill to claim. The greeting, closing and layout are added by the app.
 */
#[Temperature(0)]
final class CoverLetterWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** About how many words each length asks for. */
    private const array WORDS = ['short' => 150, 'standard' => 280];

    /** How each tone is described to the AI. */
    private const array TONES = [
        'formal' => 'formal and polite',
        'warm' => 'warm and personal, still professional',
        'confident' => 'confident and direct, without exaggerating',
    ];

    /**
     * @param  list<string>  $skills  The job's skills found in the candidate's profile.
     * @param  string  $language  "English" or "German".
     */
    public function __construct(
        private string $job,
        private string $company,
        private array $skills,
        private string $language,
        private string $tone,
        private string $length,
    ) {}

    /** The job and the facts, each marked off as data, then what to write. */
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
        $words = self::WORDS[$this->length] ?? self::WORDS['standard'];
        $tone = self::TONES[$this->tone] ?? self::TONES['formal'];

        return '<job>'.$mark($this->job).'</job>'
            ."\n<company>".$mark($this->company).'</company>'
            ."\n<skills>".$mark($this->skills === [] ? 'none' : implode(', ', $this->skills)).'</skills>'
            ."\n<facts>\n".$mark($facts)."\n</facts>"
            ."\n\nWrite in {$this->language}. Tone: {$tone}. Length: about {$words} words.";
    }

    public function instructions(): string
    {
        return Prompts::get('cover-letter');
    }

    /** The same context size as the fact checker, which checks the letter next. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->required(),
            'paragraphs' => $schema->array()->items($schema->string())->required(),
        ];
    }
}
