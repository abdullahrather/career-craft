<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An entry in a career profile section. Entries are listed by position, which the
 * person sets by moving them up and down.
 *
 * @property int $profile_id
 * @property int $position
 */
trait BelongsToProfile
{
    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
