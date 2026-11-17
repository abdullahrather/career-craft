<?php

declare(strict_types=1);

namespace App\JobSearch;

use App\Models\JobListing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\JobAlert;
use Illuminate\Support\Carbon;

/**
 * Emails each saved search that is due the jobs found since it was last checked, best
 * match first. A search with nothing new sends nothing. Only verified email addresses get
 * alerts.
 */
final class JobAlerts
{
    /** Checks every due search, and gives back how many alerts went out. */
    public static function send(Carbon $now): int
    {
        $sent = 0;
        $users = User::query()->whereNotNull('email_verified_at')->whereHas('savedSearches')->with('savedSearches')->lazyById(100);
        foreach ($users as $user) {
            $ranker = null;
            foreach ($user->savedSearches->filter(fn (SavedSearch $search): bool => $search->due($now)) as $search) {
                $filters = $search->filters();
                $new = $filters->query($user)->where('created_at', '>', $search->checked_at)->where('created_at', '<=', $now)->get(['id', 'title', 'posted_at', 'updated_at']);
                $search->update(['checked_at' => $now]);
                if ($new->isEmpty()) {
                    continue;
                }

                $ranker ??= ListingRanker::for($user);
                $ids = $ranker->canRank()
                    ? array_slice($ranker->rank($new), 0, SavedSearch::ALERT_JOBS)
                    : array_values($new->sortByDesc(fn (JobListing $listing): array => [$listing->posted_at?->getTimestamp(), $listing->id])->take(SavedSearch::ALERT_JOBS)->map(fn (JobListing $listing): int => $listing->id)->all());
                $jobs = JobListing::query()->with('sources')->whereKey($ids)->get()
                    ->sortBy(fn (JobListing $listing): int|false => array_search($listing->id, $ids, true))
                    ->map(fn (JobListing $listing): array => [
                        'title' => $listing->title,
                        'company' => $listing->company,
                        'location' => $listing->location,
                        'url' => $listing->sources->first()->url ?? route('discover', $filters->parameters()),
                        'skills' => $ranker->skillsIn($listing),
                    ]);

                $user->notify(new JobAlert($filters, $search->frequency, array_values($jobs->all()), $new->count()));
                $sent++;
            }
        }

        return $sent;
    }
}
