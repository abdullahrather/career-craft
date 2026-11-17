<?php

declare(strict_types=1);

namespace App\Jobs;

use App\JobSources\Adzuna;
use App\JobSources\Arbeitnow;
use App\JobSources\Arbeitsagentur;
use App\JobSources\ListingStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Fetches jobs from one source and saves them as listings. While it runs, a short-lived
 * cache entry tells the person's Find jobs page that new jobs are on the way.
 */
final class ImportJobListings implements ShouldQueue
{
    use Queueable;

    public const array SOURCES = ['arbeitsagentur', 'arbeitnow', 'adzuna'];

    /** How long a source isn't asked the same search again. */
    private const int FRESH_MINUTES = 60;

    public int $tries = 1;

    /** Room for the search and one details request per job, at 15 seconds each at worst. */
    public int $timeout = 500;

    public function __construct(public string $source, public string $what, public string $where, public int $userId) {}

    /**
     * The cache entry that says a source was asked this search lately. Arbeitnow has no
     * search: its feed is the same for every search.
     */
    public static function freshKey(string $source, string $what, string $where): string
    {
        return "job-source:{$source}:".md5($source === 'arbeitnow' ? '' : mb_strtolower("{$what}|{$where}"));
    }

    /** Fetches new jobs for a search from every source, unless a source was asked it lately. */
    public static function fetch(string $what, string $where, int $userId): void
    {
        foreach (self::SOURCES as $source) {
            if ($source === 'adzuna' && ! Adzuna::enabled()) {
                continue;
            }
            // A failed import gives the key back, so the search can be tried again.
            if (Cache::add(self::freshKey($source, $what, $where), true, now()->addMinutes(self::FRESH_MINUTES))) {
                Cache::put(self::runningKey($userId, $source), true, now()->addMinutes(10));
                self::dispatch($source, $what, $where, $userId);
            }
        }
    }

    /** The cache entry that says this person's import from this source is running. */
    public static function runningKey(int $userId, string $source): string
    {
        return "job-imports:{$userId}:{$source}";
    }

    public function handle(): void
    {
        try {
            $jobs = match ($this->source) {
                'arbeitsagentur' => (new Arbeitsagentur)->search($this->what, $this->where),
                'arbeitnow' => (new Arbeitnow)->latest(),
                'adzuna' => Adzuna::enabled() ? (new Adzuna)->search($this->what, $this->where) : [],
                default => [],
            };
            ListingStore::save($jobs);
        } catch (Throwable $exception) {
            // A source that's down leaves the others' jobs, and the jobs it gave before. The
            // search may be tried again straight away.
            report($exception);
            Cache::forget(self::freshKey($this->source, $this->what, $this->where));
        } finally {
            Cache::forget(self::runningKey($this->userId, $this->source));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::runningKey($this->userId, $this->source));
        Cache::forget(self::freshKey($this->source, $this->what, $this->where));
    }
}
