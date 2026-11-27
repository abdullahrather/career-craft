<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\OllamaOptions;
use App\Ai\Prompts;
use App\Models\JobPost;
use App\Models\MockInterview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Gives feedback on one answer in a mock interview: which parts of the STAR method it has,
 * how well it shows what the job asks for, what to improve, and a stronger answer.
 */
#[Temperature(0)]
final class AnswerReviewer implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(private MockInterview $interview) {}

    /**
     * The job, the question and the answers, each marked off as data, then the language.
     *
     * @param  array{index: int, question: string, answer: string, follow_up: string|null, follow_up_answer: string|null}  $answer
     */
    public function input(array $answer): string
    {
        $mark = function (string $value): string {
            // Until nothing changes: removing one marker can join the rest of a nested one.
            do {
                $before = $value;
                $value = (string) preg_replace('/<\/?(?:job|seniority|required|question|answer|follow-up|follow-up-answer)>/i', '', $value);
            } while ($value !== $before);

            return $value;
        };
        $application = $this->interview->application;
        $post = $application->jobPost?->status === JobPost::READY ? $application->jobPost : null;
        $skills = $post === null ? [] : $post->required_skills;

        return '<job>'.$mark($application->title).'</job>'
            ."\n<seniority>".$mark($post !== null && $post->seniority !== '' ? $post->seniority : 'unknown').'</seniority>'
            ."\n<required>".$mark($skills === [] ? 'unknown' : implode(', ', $skills)).'</required>'
            ."\n<question>".$mark($answer['question']).'</question>'
            ."\n<answer>".$mark($answer['answer']).'</answer>'
            .($answer['follow_up'] === null ? '' : "\n<follow-up>".$mark($answer['follow_up']).'</follow-up>'
                ."\n<follow-up-answer>".$mark((string) $answer['follow_up_answer']).'</follow-up-answer>')
            ."\n\nWrite in ".($this->interview->locale === 'de' ? 'German' : 'English').'.';
    }

    public function instructions(): string
    {
        return Prompts::get('answer-review');
    }

    /** The same context size as the interviewer, which runs just before. */
    public function providerOptions(Lab|string $provider): array
    {
        return OllamaOptions::for($provider, 8192);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'story' => $schema->boolean()->required(),
            'situation' => $schema->boolean()->required(),
            'task' => $schema->boolean()->required(),
            'action' => $schema->boolean()->required(),
            'result' => $schema->boolean()->required(),
            'fit' => $schema->integer()->min(1)->max(5)->required(),
            'feedback' => $schema->string()->required(),
            'example' => $schema->string()->required(),
        ];
    }
}
