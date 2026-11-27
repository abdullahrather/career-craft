<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AnswerReviewer;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Models\MockInterview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI give feedback on the next answer of a finished mock interview, then queues
 * itself for the one after. One answer per job keeps each within the queue's time limit,
 * and trying again after a failure goes on from the answer that failed.
 */
final class ReviewInterview implements ShouldQueue
{
    use Queueable;

    /** The longest feedback or example answer kept. */
    public const int TEXT_LENGTH = 3000;

    public int $tries = 1;

    /** Room for one answer at the longest AI timeout. */
    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public MockInterview $interview) {}

    public function handle(AiRunner $ai): void
    {
        $interview = $this->interview;
        if ($interview->feedback_status !== 'writing') {
            return;
        }
        $done = array_column($interview->feedback ?? [], 'index');
        $answer = collect($interview->answers())->first(fn (array $answer): bool => ! in_array($answer['index'], $done, true));
        if ($answer === null) {
            $interview->update(['feedback_status' => 'ready']);

            return;
        }

        $agent = new AnswerReviewer($interview);
        try {
            $response = $ai->prompt($agent, $agent->input($answer), $interview->application->user, 'interview_feedback');
        } catch (Throwable $exception) {
            if (! $exception instanceof AiLimitReached) {
                report($exception);
            }
            $interview->update(['feedback_status' => 'failed']);

            return;
        }

        $written = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
        $text = fn (string $key): string => is_string($written[$key] ?? null) ? Str::limit(trim($written[$key]), self::TEXT_LENGTH, '') : '';
        $fit = $written['fit'] ?? null;
        if ($text('feedback') === '' || $text('example') === '' || ! is_int($fit)) {
            $interview->update(['feedback_status' => 'failed']);

            return;
        }
        $part = fn (string $key): bool => ($written[$key] ?? false) === true;

        $interview->update(['feedback' => [...$interview->feedback ?? [], [
            'index' => $answer['index'],
            'story' => $part('story'),
            'star' => ['situation' => $part('situation'), 'task' => $part('task'), 'action' => $part('action'), 'result' => $part('result')],
            'fit' => max(1, min(5, $fit)),
            'feedback' => $text('feedback'),
            'example' => self::numbersSaid($text('example'), $answer['answer'].' '.$answer['follow_up_answer'], $interview->locale),
        ]]]);
        self::dispatch($interview);
    }

    /**
     * The example answer with each number the candidate didn't say made a placeholder: the
     * local model adds results such as "40% faster" although it's told not to.
     */
    private static function numbersSaid(string $example, string $said, string $locale): string
    {
        $number = '/(?<![\d.,])\d+(?:[.,]\d+)*/';
        preg_match_all($number, $said, $matches);
        $numbers = $matches[0];

        return (string) preg_replace_callback($number, fn (array $match): string => in_array($match[0], $numbers, true) ? $match[0] : __('[number]', [], $locale), $example);
    }

    public function failed(?Throwable $exception): void
    {
        $this->interview->update(['feedback_status' => 'failed']);
    }
}
