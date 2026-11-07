<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A link on the profile, such as a portfolio or LinkedIn.
 *
 * @property int $id
 * @property string $label
 * @property string $url
 */
#[Fillable(['label', 'url', 'position'])]
final class ProfileLink extends Model
{
    use BelongsToProfile;

    protected $table = 'profile_links';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
    ];
}
