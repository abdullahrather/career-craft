<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI request: who, what for, which model, how many tokens and what it cost.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $feature
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property string $cost
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'cost'])]
final class AiUsage extends Model
{
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Tokens the person has used since midnight. */
    public static function tokensToday(User $user): int
    {
        $total = self::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->selectRaw('coalesce(sum(input_tokens + output_tokens), 0) as total')
            ->value('total');

        return is_numeric($total) ? (int) $total : 0;
    }
}
