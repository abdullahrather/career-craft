<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\AnswerWriter;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\FactGuard;
use App\Ai\ProfileFacts;
use App\JobSearch\JobMatch;
use App\Models\ApplyQueueItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Writes the answers to "why this company" and "why you" for a queued job, in the post's
 * language, and checks each against the profile. What the check can't find is kept with the
 * answer, to show before it is pasted anywhere.
 */
final class WriteAnswers implements ShouldQueue
{
    use Queueable;

    /** The questions, in the order shown. */
    public const array QUESTIONS = ['why_company', 'why_fit'];

    public int $tries = 1;

    /** Room for the answers and each one's check, at the longest AI timeout. */
    public int $timeout = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ApplyQueueItem $item) {}

    public function handle(AiRunner $ai, FactGuard $guard): void
    {
        $item = $this->item;
        $post = $item->jobPost;
        if ($post === null) {
            $item->update(['answers_status' => 'failed']);

            return;
        }
        $user = $item->user;
        $profile = $user->careerProfile()->load(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        $facts = ProfileFacts::text($profile);
        $skills = JobMatch::found($post, $profile);
        $agent = new AnswerWriter($post->title, $post->company, $skills, $post->language === 'de' ? 'German' : 'English');
        // The title and company may be named; anything else must come from the profile.
        $sources = $facts."\nApplying for: {$post->title} at {$post->company}\nThe job's names for skills the candidate has: ".implode(', ', $skills);

        try {
            $response = $ai->prompt($agent, $agent->input($facts), $user, 'application_answers');
            $written = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
            $answers = [];
            foreach (self::QUESTIONS as $question) {
                $text = is_string($written[$question] ?? null) ? Str::limit(trim($written[$question]), 2000, '') : '';
                if ($text === '') {
                    $item->update(['answers_status' => 'failed']);

                    return;
                }
                $checked = true;
                $flags = [];
                try {
                    $flags = $guard->check($text, $sources, $user, ProfileFacts::jobs($profile));
                } catch (AiLimitReached $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    $checked = false;
                }
                // Naming the company applied to is never new, however the checker quotes it.
                $flags = array_values(array_filter($flags, fn (array $flag): bool => $post->company === '' || $flag['kind'] !== 'employer' || ! Str::contains($flag['quote'], $post->company, ignoreCase: true)));
                $answers[] = ['question' => $question, 'text' => $text, 'flags' => $flags, 'checked' => $checked];
            }
        } catch (Throwable $exception) {
            if (! $exception instanceof AiLimitReached) {
                report($exception);
            }
            $item->update(['answers_status' => 'failed']);

            return;
        }

        $item->update(['answers_status' => 'ready', 'answers' => $answers]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->item->update(['answers_status' => 'failed']);
    }
}
