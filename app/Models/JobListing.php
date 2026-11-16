<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job from a public job source, shared by everyone, with every source it was found on.
 *
 * @property int $id
 * @property string $title
 * @property string $company
 * @property string $location
 * @property string $work_mode
 * @property int|null $salary_min
 * @property int|null $salary_max
 * @property string|null $salary_currency
 * @property string|null $salary_period
 * @property string $description
 * @property Carbon|null $posted_at
 * @property string $fingerprint
 * @property Carbon $last_seen_at
 * @property-read Collection<int, JobListingSource> $sources
 */
#[Fillable(['title', 'company', 'location', 'work_mode', 'salary_min', 'salary_max', 'salary_currency', 'salary_period', 'description', 'posted_at', 'fingerprint', 'last_seen_at'])]
final class JobListing extends Model
{
    /** How long a job stays in the list after a source last showed it. */
    public const int KEEP_DAYS = 30;

    /** @return HasMany<JobListingSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(JobListingSource::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['posted_at' => 'datetime', 'last_seen_at' => 'datetime', 'salary_min' => 'integer', 'salary_max' => 'integer'];
    }
}
