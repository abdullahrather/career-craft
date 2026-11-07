<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A job, with the achievements a CV lists under it.
 *
 * @property int $id
 * @property string $title
 * @property string $employer
 * @property string $city
 * @property string $country
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $description
 * @property list<string> $achievements
 */
#[Fillable(['title', 'employer', 'city', 'country', 'starts_on', 'ends_on', 'description', 'achievements', 'position'])]
final class Experience extends Model
{
    use BelongsToProfile;

    protected $table = 'experiences';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
        'city' => '',
        'country' => '',
        'achievements' => '[]',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'achievements' => 'array',
        ];
    }
}
