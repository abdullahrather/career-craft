<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A certificate or licence.
 *
 * @property int $id
 * @property string $name
 * @property string $issuer
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string $url
 */
#[Fillable(['name', 'issuer', 'issued_on', 'expires_on', 'url', 'position'])]
final class Certification extends Model
{
    use BelongsToProfile;

    protected $table = 'certifications';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
        'issuer' => '',
        'url' => '',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'expires_on' => 'date',
        ];
    }
}
