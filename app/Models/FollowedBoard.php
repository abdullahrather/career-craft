<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company job board a person follows: its jobs join the shared list, and are fetched
 * again every day.
 *
 * @property int $id
 * @property int $user_id
 * @property string $board
 * @property string $slug
 * @property string $name
 */
#[Fillable(['board', 'slug', 'name'])]
final class FollowedBoard extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
