<?php

declare(strict_types=1);

namespace App\Jobs;

use App\JobSources\CompanyBoards;
use App\JobSources\ListingStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Fetches every open job on a followed company board, for the daily refresh. */
final class ImportCompanyBoard implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $board, public string $slug) {}

    public function handle(): void
    {
        try {
            ListingStore::save(CompanyBoards::jobs($this->board, $this->slug));
        } catch (Throwable $exception) {
            // A board that's down, or gone, leaves the jobs it gave before; tomorrow tries again.
            report($exception);
        }
    }
}
