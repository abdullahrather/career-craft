<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use App\Models\JobPost;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Writes the questions an interviewer would likely ask about one job post: its duties, the
 * skills it asks for and its seniority. It writes about the job only, so it needs no facts
 * from the candidate's profile.
 */
#[Temperature(0)]
final class InterviewQuestioner implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** The longest part of the post's text the AI gets. */
    public const int POST_LENGTH = 8000;

    public function __construct(private JobPost $post) {}

    /** The job, each part marked off as data, then the language. */
    public function input(): string
    {
        $mark = function (string $value): string {
            // Until nothing changes: removing one marker can join the rest of a nested one.
            do {
                $before = $value;
                $value = (string) preg_replace('/<\/?(?:job|company|seniority|required|nice|post)>/i', '', $value);
            } while ($value !== $before);

            return $value;
        };
        $post = $this->post;

        return '<job>'.$mark($post->title).'</job>'
            ."\n<company>".$mark($post->company).'</company>'
            ."\n<seniority>".$mark($post->seniority !== '' ? $post->seniority : 'unknown').'</seniority>'
            ."\n<required>".$mark(self::skills($post->required_skills)).'</required>'
            ."\n<nice>".$mark(self::skills($post->nice_skills)).'</nice>'
            ."\n<post>\n".$mark(Str::limit((string) $post->text, self::POST_LENGTH))."\n</post>"
            ."\n\nWrite in ".($post->language === 'de' ? 'German' : 'English').'.';
    }

    /** @param  list<string>  $skills */
    private static function skills(array $skills): string
    {
        return $skills === [] ? 'none' : implode(', ', $skills);
    }

    public function instructions(): string
    {
        return Prompts::get('interview-questions');
    }

    /** Room for a long post and the answer, as for reading the post. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 16384);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()->items($schema->object([
                'question' => $schema->string()->required(),
                'about' => $schema->string()->required(),
            ]))->required(),
        ];
    }
}
