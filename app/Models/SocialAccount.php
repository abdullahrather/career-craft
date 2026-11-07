<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Google or GitHub account someone signs in with.
 *
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $provider_id
 */
#[Fillable(['provider', 'provider_id'])]
final class SocialAccount extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
