<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A skill, from 1 (basic) to 4 (expert).
 *
 * @property int $id
 * @property string $name
 * @property int $level
 */
#[Fillable(['name', 'level', 'position'])]
final class Skill extends Model
{
    use BelongsToProfile;

    protected $table = 'skills';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
        'level' => 2,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }
}
