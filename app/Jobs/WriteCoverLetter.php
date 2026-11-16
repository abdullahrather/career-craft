<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Agents\CoverLetterWriter;
use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Ai\FactGuard;
use App\Ai\ProfileFacts;
use App\JobSearch\JobMatch;
use App\Models\CoverLetter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Has the AI write a cover letter, then has the fact guard check each paragraph against
 * the profile. The job's title and company count as known, so naming them isn't flagged;
 * the rest of the post doesn't, so a skill the post asks for but the profile lacks is.
 */
#[DeleteWhenMissingModels]
final class WriteCoverLetter implements ShouldQueue
{
    use Queueable;

    /** The most paragraphs kept: a letter is short. */
    private const int MAX_PARAGRAPHS = 6;

    public int $tries = 1;

    /** Room for three tries of the letter and of each paragraph's check, at the longest AI timeout. */
    public int $timeout = 600;

    public function __construct(public CoverLetter $letter) {}

    public function handle(AiRunner $ai, FactGuard $guard): void
    {
        $letter = $this->letter;
        $user = $letter->user;
        $post = $letter->jobPost;
        $profile = $user->careerProfile()->load(['experiences', 'educations', 'projects', 'skills', 'certifications', 'languages']);
        $facts = ProfileFacts::text($profile);
        $skills = JobMatch::found($post, $profile);
        $agent = new CoverLetterWriter($post->title ?? '', $post->company ?? '', $skills, $letter->language === 'de' ? 'German' : 'English', $letter->tone, $letter->length);

        try {
            $response = $ai->prompt($agent, $agent->input($facts), $user, 'cover_letter');
            $answer = $response instanceof StructuredAgentResponse ? $response->toArray() : [];
            $subject = is_string($answer['subject'] ?? null) ? Str::limit(trim($answer['subject']), 250, '') : '';
            $paragraphs = array_slice(array_values(array_filter(array_map(
                fn (mixed $paragraph): string => is_string($paragraph) ? self::withoutGreeting(trim($paragraph)) : '',
                is_array($answer['paragraphs'] ?? null) ? $answer['paragraphs'] : [],
            ))), 0, self::MAX_PARAGRAPHS);
            if ($paragraphs === []) {
                $this->markFailed('ai');

                return;
            }

            // The title and company may be named; anything else must come from the profile.
            $sources = $facts."\nApplying for: ".($post->title ?? '').' at '.($post->company ?? '')
                ."\nThe job's names for skills the candidate has: ".implode(', ', $skills);
            $flags = [];
            $checked = true;
            foreach (array_filter([$subject, ...$paragraphs]) as $text) {
                try {
                    $flags = [...$flags, ...$guard->check($text, $sources, $user, ProfileFacts::jobs($profile))];
                } catch (AiLimitReached $exception) {
                    throw $exception;
                } catch (Throwable $exception) {
                    report($exception);
                    $checked = false;
                }
            }
        } catch (AiLimitReached) {
            $this->markFailed('limit');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed('ai');

            return;
        }

        // Naming the company applied to is never new, however the checker quotes it ("bei der Acme GmbH").
        $company = $post->company ?? '';
        $flags = array_filter($flags, fn (array $flag): bool => $company === '' || $flag['kind'] !== 'employer' || ! Str::contains($flag['quote'], $company, ignoreCase: true));

        $letter->update([
            'status' => CoverLetter::READY,
            'error' => null,
            'subject' => $subject,
            'body' => implode("\n\n", $paragraphs),
            'flags' => array_values(array_unique($flags, SORT_REGULAR)),
            'checked' => $checked,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed('ai');
    }

    /**
     * A paragraph without the greeting or closing the AI may write anyway, though asked not
     * to: the letter adds its own. A paragraph that is only one becomes empty.
     */
    private static function withoutGreeting(string $paragraph): string
    {
        $paragraph = (string) preg_replace('/^(?:Sehr geehrte[^,\n]*|Guten Tag[^,\n]*|Hallo[^,\n]*|Dear [^,\n]*|Hello[^,\n]*),\s*/u', '', $paragraph);
        if (preg_match('/^(?:Mit freundlichen Grüßen|Freundliche Grüße|Beste Grüße|Kind regards|Best regards|Yours sincerely|Sincerely)\b/iu', $paragraph) === 1) {
            return '';
        }

        return mb_strtoupper(mb_substr($paragraph, 0, 1)).mb_substr($paragraph, 1);
    }

    private function markFailed(string $reason): void
    {
        $this->letter->update(['status' => CoverLetter::FAILED, 'error' => $reason]);
    }
}
