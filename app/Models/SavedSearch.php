<?php

declare(strict_types=1);

namespace App\Models;

use App\JobSearch\ListingFilters;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A search a person saved on Find jobs, which emails them its new jobs.
 *
 * @property int $id
 * @property int $user_id
 * @property string $what
 * @property string $where
 * @property bool $remote
 * @property string $mode
 * @property bool $salary
 * @property string $company
 * @property string $frequency
 * @property Carbon $checked_at
 * @property-read User $user
 */
#[Fillable(['what', 'where', 'remote', 'mode', 'salary', 'company', 'frequency', 'checked_at'])]
final class SavedSearch extends Model
{
    public const array FREQUENCIES = ['daily', 'weekly'];

    /** How many searches one person can save. */
    public const int MAX = 20;

    /** How many jobs one alert lists; the rest are a link away. */
    public const int ALERT_JOBS = 10;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function filters(): ListingFilters
    {
        return new ListingFilters($this->what, $this->where, $this->remote, $this->mode, $this->salary, $this->company);
    }

    /** Whether it's time for the next alert. An hour's slack lets a late run still count. */
    public function due(Carbon $now): bool
    {
        return $this->checked_at->lte($this->frequency === 'weekly' ? $now->copy()->subWeek()->addHour() : $now->copy()->subDay()->addHour());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['remote' => 'boolean', 'salary' => 'boolean', 'checked_at' => 'datetime'];
    }
}
