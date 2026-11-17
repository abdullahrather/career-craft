<?php

declare(strict_types=1);

namespace App\Applications;

use App\Ai\AiLimitReached;
use App\Ai\AiRunner;
use App\Jobs\AnalyseJobPost;
use App\Jobs\PrepareApplication;
use App\Models\Application;
use App\Models\ApplyQueueItem;
use App\Models\JobListing;
use App\Models\JobListingSource;
use App\Models\JobPost;
use App\Models\ResumeVersion;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * Adds jobs to the apply queue. Each one is analysed, then gets a tailored CV and a letter in
 * the background. A person can queue a limited number a day, and only while the AI allowance
 * lasts.
 */
final readonly class ApplyQueue
{
    public function __construct(private AiRunner $ai, private SentDocuments $documents) {}

    /** Queues a job, or says why it can't be. */
    public function add(User $user, JobListing $listing): ApplyQueueItem|string
    {
        $base = $user->apply_resume_id !== null ? $user->resumes()->find($user->apply_resume_id) : null;
        if ($base === null) {
            return __('Choose the CV to start from on the Apply queue page first.');
        }
        if ($user->applyQueueItems()->where('job_listing_id', $listing->id)->whereNull('skipped_at')->exists()) {
            return __('This job is in your apply queue already.');
        }
        if ($user->applyQueueItems()->where('created_at', '>=', today())->count() >= ApplyQueueItem::DAILY) {
            return __('You can queue :count jobs a day. Review the ones prepared, and queue more tomorrow.', ['count' => ApplyQueueItem::DAILY]);
        }
        if ($this->ai->remaining($user) === 0) {
            return (new AiLimitReached)->getMessage();
        }

        $post = JobPost::fromListing($user, $listing);
        $item = $user->applyQueueItems()->create([
            'job_listing_id' => $listing->id,
            'job_post_id' => $post->id,
            'base_resume_id' => $base->id,
            'title' => mb_substr($listing->title, 0, 250),
            'company' => mb_substr($listing->company, 0, 250),
            'tone' => $user->apply_tone,
        ]);
        Bus::chain([new AnalyseJobPost($post), new PrepareApplication($item)])->dispatch();

        return $item;
    }

    /**
     * The person's go-ahead: the tailored CV is saved as a version, and the job becomes an
     * application in Applied with copies of the CV and letter. Says what's missing instead,
     * if anything is.
     *
     * @return Application|array{field: string, message: string}
     */
    public function approve(ApplyQueueItem $item): Application|array
    {
        $post = $item->jobPost;
        $resume = $item->resume;
        $letter = $item->coverLetter;
        if ($item->state() !== 'ready' || $post === null || $resume === null || $letter === null) {
            return ['field' => 'approve', 'message' => __('This application isn\'t ready to approve.')];
        }
        if (! $letter->sendable()) {
            return ['field' => 'approve', 'message' => __('Finish the letter first: confirm or remove what is marked in it.')];
        }
        // A version saved by a try that failed later is used again, unless the CV changed since.
        $version = $item->resume_version_id !== null ? $resume->versions()->find($item->resume_version_id) : null;
        $lastChange = $resume->changes()->max('updated_at');
        $changed = max($resume->updated_at->getTimestamp(), is_string($lastChange) ? Carbon::parse($lastChange)->getTimestamp() : 0);
        if ($version === null || $version->created_at->getTimestamp() < $changed) {
            try {
                $version = ResumeVersion::saveFrom($resume);
            } catch (ConnectionException|RequestException $exception) {
                report($exception);

                return ['field' => 'approve', 'message' => __('The PDF could not be made. Check that the PDF service is running: docker compose up -d gotenberg')];
            }
            $item->update(['resume_version_id' => $version->id]);
        }

        $user = $item->user;
        $source = $item->job_listing_id !== null ? JobListing::query()->with('sources')->find($item->job_listing_id)?->sources->first() : null;
        $application = $user->applications()->where('job_post_id', $post->id)->first() ?? Application::track($user, [
            'job_post_id' => $post->id,
            'title' => $post->title !== '' ? $post->title : $item->title,
            'company' => $post->company,
            'location' => $post->location,
            'url' => $post->url,
            'source' => $source !== null ? (JobListingSource::NAMES[$source->source] ?? $source->source) : (string) preg_replace('/^www\./', '', (string) parse_url((string) $post->url, PHP_URL_HOST)),
        ]);
        $error = $this->documents->record($application, $version, $letter);
        if ($error !== null) {
            // The review page shows approving's errors in one place.
            return ['field' => 'approve', 'message' => $error['message']];
        }
        $application->log('queue');
        if ($application->status === 'saved') {
            $application->moveTo('applied');
        }
        $item->update(['approved_at' => now(), 'application_id' => $application->id]);

        return $application;
    }
}
