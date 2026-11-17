<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ApplyQueueItem;
use App\Models\CoverLetter;
use App\Models\JobPost;
use App\Resumes\Tailoring;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * The second step of preparing a queued application, after the post is analysed: a tailored
 * copy of the person's CV, and a letter in the post's language sent with it. Both are
 * written by the AI in the background, and both wait for the person's review.
 */
final class PrepareApplication implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public ApplyQueueItem $item) {}

    public function handle(): void
    {
        $item = $this->item;
        $post = $item->jobPost;
        if ($post === null || $post->status !== JobPost::READY || $item->skipped_at !== null) {
            return;
        }
        $base = $item->baseResume;
        if ($base === null) {
            $item->update(['error' => 'no-cv']);

            return;
        }

        $resume = Tailoring::start($base, $post);
        $letter = $item->user->coverLetters()->create([
            'job_post_id' => $post->id,
            'resume_id' => $resume->id,
            'language' => $post->language === 'de' ? 'de' : 'en',
            'tone' => $item->tone,
            'length' => 'standard',
            'recipient' => implode("\n", array_filter([$post->company, $post->location])),
            'status' => CoverLetter::WRITING,
        ]);
        WriteCoverLetter::dispatch($letter);
        $item->update(['resume_id' => $resume->id, 'cover_letter_id' => $letter->id, 'prepared_at' => now(), 'title' => $post->title !== '' ? mb_substr($post->title, 0, 250) : $item->title, 'company' => $post->company !== '' ? mb_substr($post->company, 0, 250) : $item->company]);
    }

    /** Something went wrong mid-way: the item shows as stopped, not as preparing for ever. */
    public function failed(?Throwable $exception): void
    {
        $this->item->update(['error' => 'prepare-failed']);
    }
}
