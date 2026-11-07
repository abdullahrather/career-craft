<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A project worth showing, at work or on the side.
 *
 * @property int $id
 * @property string $name
 * @property string $role
 * @property string $url
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $description
 * @property list<string> $achievements
 */
#[Fillable(['name', 'role', 'url', 'starts_on', 'ends_on', 'description', 'achievements', 'position'])]
final class Project extends Model
{
    use BelongsToProfile;

    protected $table = 'projects';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
        'role' => '',
        'url' => '',
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
