<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a listing was found: the source, its own id for the job, and the job's page there.
 *
 * @property int $id
 * @property int $job_listing_id
 * @property string $source
 * @property string $external_id
 * @property string $url
 * @property-read JobListing $listing
 */
#[Fillable(['source', 'external_id', 'url'])]
final class JobListingSource extends Model
{
    /** Each source and the name shown for it. */
    public const array NAMES = ['arbeitsagentur' => 'Bundesagentur für Arbeit', 'arbeitnow' => 'Arbeitnow', 'adzuna' => 'Adzuna'];

    /** @return BelongsTo<JobListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }
}
