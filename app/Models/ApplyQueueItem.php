<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A job in the apply queue: analysed, then given a tailored CV and a letter, ready for the
 * person to review and approve. Nothing goes out without that.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $job_listing_id
 * @property int|null $job_post_id
 * @property int|null $base_resume_id
 * @property int|null $resume_id
 * @property int|null $cover_letter_id
 * @property int|null $application_id
 * @property int|null $resume_version_id
 * @property string $title
 * @property string $company
 * @property string $tone
 * @property string|null $error
 * @property Carbon|null $prepared_at
 * @property string|null $answers_status writing, ready or failed.
 * @property list<array{question: string, text: string, flags: list<array{quote: string, kind: string}>, checked: bool}>|null $answers
 * @property Carbon|null $approved_at
 * @property Carbon|null $skipped_at
 * @property Carbon $created_at
 * @property-read User $user
 * @property-read JobPost|null $jobPost
 * @property-read Resume|null $resume
 * @property-read Resume|null $baseResume
 * @property-read CoverLetter|null $coverLetter
 * @property-read Application|null $application
 */
#[Fillable(['job_listing_id', 'job_post_id', 'base_resume_id', 'resume_id', 'cover_letter_id', 'application_id', 'resume_version_id', 'title', 'company', 'tone', 'error', 'prepared_at', 'approved_at', 'skipped_at', 'answers_status', 'answers'])]
final class ApplyQueueItem extends Model
{
    /** How many jobs one person can queue a day: each one costs AI time, and deserves a careful look. */
    public const int DAILY = 10;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<JobPost, $this> */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    /** @return BelongsTo<Resume, $this> */
    public function baseResume(): BelongsTo
    {
        return $this->belongsTo(Resume::class, 'base_resume_id');
    }

    /** @return BelongsTo<Resume, $this> */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /** @return BelongsTo<CoverLetter, $this> */
    public function coverLetter(): BelongsTo
    {
        return $this->belongsTo(CoverLetter::class);
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Where it stands: preparing, ready (to review), failed, approved or skipped. Worked out
     * from the post, the tailored CV and the letter, so it is never out of step with them.
     */
    public function state(): string
    {
        return match (true) {
            $this->approved_at !== null => 'approved',
            $this->skipped_at !== null => 'skipped',
            $this->error !== null => 'failed',
            $this->jobPost === null => 'failed',
            $this->jobPost->status === JobPost::FAILED => 'failed',
            $this->jobPost->status === JobPost::READING, $this->prepared_at === null => 'preparing',
            // Prepared, but the tailored CV or the letter was deleted since.
            $this->resume === null, $this->coverLetter === null => 'failed',
            $this->resume->changes()->where('status', TailoredChange::WAITING)->exists(), $this->coverLetter->status === CoverLetter::WRITING => 'preparing',
            // The first answers are part of preparing; writing them again isn't.
            $this->answers_status === 'writing' && $this->answers === null => 'preparing',
            // A letter the AI couldn't write can be tried again on the review page.
            default => 'ready',
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['answers' => 'array', 'prepared_at' => 'datetime', 'approved_at' => 'datetime', 'skipped_at' => 'datetime'];
    }
}
