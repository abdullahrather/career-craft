<?php

declare(strict_types=1);

namespace App\JobSources;

use App\Models\JobListing;
use App\Models\JobListingSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Saves found jobs as listings. A job a source has shown before updates its listing; a job
 * another source already has (the same fingerprint) joins that listing as a second source.
 */
final class ListingStore
{
    /**
     * @param  list<FoundJob>  $jobs
     * @return int How many listings were new.
     */
    public static function save(array $jobs): int
    {
        $new = 0;
        foreach ($jobs as $job) {
            // The link is shown and may be read: only a web address, never "javascript:".
            if ($job->title === '' || $job->externalId === '' || preg_match('/^https?:\/\//i', $job->url) !== 1) {
                continue;
            }
            // Another import can create the same listing between the lookup and the insert:
            // then the job is saved once more, and finds that listing.
            try {
                $new += self::saveOne($job);
            } catch (UniqueConstraintViolationException) {
                $new += self::saveOne($job);
            }
        }

        return $new;
    }

    /** @return int 1 when the listing is new. */
    private static function saveOne(FoundJob $job): int
    {
        return DB::transaction(function () use ($job): int {
            $fingerprint = Normalise::fingerprint($job->title, $job->company, $job->location);
            $source = JobListingSource::query()->where('source', $job->source)->where('external_id', $job->externalId)->first();
            $listing = $source !== null ? $source->listing : JobListing::query()->where('fingerprint', $fingerprint)->first();
            $created = $listing === null;

            if ($listing === null) {
                $listing = JobListing::query()->create([...self::fields($job), 'fingerprint' => $fingerprint]);
            } else {
                // What another source already knows stays, unless this one knows more. A job's
                // only source may update its description, even to a shorter one.
                $fields = array_filter(self::fields($job), fn (mixed $value): bool => $value !== null && $value !== '');
                $onlySource = $source !== null && $listing->sources()->count() === 1;
                if (! $onlySource && mb_strlen($job->description) <= mb_strlen($listing->description)) {
                    unset($fields['description']);
                }
                $listing->fill($fields);
                $listing->last_seen_at = now();
                $listing->save();
            }

            if ($source === null) {
                $listing->sources()->create(['source' => $job->source, 'external_id' => $job->externalId, 'url' => $job->url]);
            } else {
                $source->update(['url' => $job->url]);
            }

            return (int) $created;
        });
    }

    /** @return array<string, mixed> */
    private static function fields(FoundJob $job): array
    {
        return [
            'title' => $job->title,
            'company' => $job->company,
            'location' => $job->location,
            'work_mode' => $job->workMode,
            'salary_min' => $job->salaryMin,
            'salary_max' => $job->salaryMax,
            'salary_currency' => $job->salaryCurrency,
            'salary_period' => $job->salaryPeriod,
            'description' => $job->description,
            'posted_at' => $job->postedAt,
            'last_seen_at' => now(),
        ];
    }
}
