<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\InterviewQuestioner;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Models\Application;
use App\Models\JobPost;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI write interview questions from an application's analysed job post, in the
 * post's language, and keeps them with the application for practice.
 */
final class WriteQuestions implements ShouldQueue
{
    use Queueable;

    /** The most questions kept. */
    public const int COUNT = 8;

    public int $tries = 1;

    /** Room for a long post at the longest AI timeout. */
    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Application $application) {}

    public function handle(AiRunner $ai): void
    {
        $application = $this->application;
        $post = $application->jobPost;
        if ($post === null || $post->status !== JobPost::READY) {
            $application->update(['questions_status' => 'failed']);

            return;
        }

        $agent = new InterviewQuestioner($post);
        try {
            $response = $ai->prompt($agent, $agent->input(), $application->user, 'interview_questions');
        } catch (Throwable $exception) {
            if (! $exception instanceof AiLimitReached) {
                report($exception);
            }
            $application->update(['questions_status' => 'failed']);

            return;
        }

        $written = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
        $questions = [];
        foreach (is_array($written['questions'] ?? null) ? $written['questions'] : [] as $item) {
            $question = is_array($item) && is_string($item['question'] ?? null) ? Str::limit(trim($item['question']), 500, '') : '';
            if ($question !== '') {
                $questions[] = ['question' => $question, 'about' => is_string($item['about'] ?? null) ? Str::limit(trim($item['about']), 100, '') : ''];
            }
        }
        $questions = array_slice($questions, 0, self::COUNT);

        $application->update($questions === [] ? ['questions_status' => 'failed'] : ['questions_status' => 'ready', 'questions' => $questions]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->application->update(['questions_status' => 'failed']);
    }
}
