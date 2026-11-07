<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A language, at a CEFR level from A1 to C2, or native.
 *
 * @property int $id
 * @property string $name
 * @property string $level
 */
#[Fillable(['name', 'level', 'position'])]
final class ProfileLanguage extends Model
{
    use BelongsToProfile;

    protected $table = 'profile_languages';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
    ];
}
