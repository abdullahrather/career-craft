<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job a person marked "not interested", with its title, which outlives the job.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $job_listing_id
 * @property string $title
 */
#[Fillable(['job_listing_id', 'title'])]
final class DismissedListing extends Model
{
    /** How many of the latest dismissed titles tune the ranking. */
    public const int RANKED = 200;

    /** @return BelongsTo<JobListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }
}
