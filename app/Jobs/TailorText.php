<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\Tailor;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\FactGuard;
use App\Ai\ProfileFacts;
use App\JobSearch\JobMatch;
use App\Models\TailoredChange;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI reword one text of a tailored CV for its job, then has the fact guard check
 * the result against the profile. One job per text, so each stays well within the queue's
 * time limit, and the candidate sees the first suggestions while the rest are written.
 */
#[DeleteWhenMissingModels]
final class TailorText implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Room for three tries of the rewording and of each fact check, at the longest AI timeout. */
    public int $timeout = 600;

    public function __construct(public TailoredChange $change) {}

    public function handle(AiRunner $ai, FactGuard $guard): void
    {
        $change = $this->change;
        if ($change->status !== TailoredChange::WAITING) {
            return;
        }
        $resume = $change->resume;
        $user = $resume->user;
        $post = $resume->jobPost;
        $profile = $user->careerProfile()->load(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        // Only the job's skills the profile has: the AI can bring them forward, but has no missing one to add.
        $match = $post === null ? ['required' => [], 'nice' => []] : JobMatch::of($post, $profile);
        $skills = array_column(array_filter([...$match['required'], ...$match['nice']], fn (array $item): bool => $item['where'] !== null), 'skill');
        $agent = new Tailor($post->title ?? '', $skills, $resume->language === 'de' ? 'German' : 'English');

        try {
            $response = $ai->prompt($agent, $agent->input($change->original), $user, 'tailor_cv');
            $answer = $response instanceof StructuredAgentResponse ? ($response->toArray()['text'] ?? null) : null;
            $suggestion = is_string($answer) ? trim($answer) : '';
            if ($suggestion === '' || $suggestion === $change->original) {
                $change->update(['status' => TailoredChange::UNCHANGED]);

                return;
            }

            // When the guard can't run, the suggestion is marked as unchecked rather than passed.
            try {
                $flags = $guard->check($suggestion, ProfileFacts::text($profile)."\nText being edited: {$change->original}", $user, ProfileFacts::jobs($profile));
                $checked = true;
            } catch (AiLimitReached $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $flags = [];
                $checked = false;
            }
        } catch (AiLimitReached) {
            $this->markFailed('limit');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed('ai');

            return;
        }

        $change->update(['status' => TailoredChange::READY, 'suggestion' => $suggestion, 'flags' => $flags, 'checked' => $checked]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('ai');
    }

    private function markFailed(string $reason): void
    {
        $this->change->update(['status' => TailoredChange::FAILED, 'error' => $reason]);
    }
}
