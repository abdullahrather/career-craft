<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A degree, apprenticeship or school.
 *
 * @property int $id
 * @property string $institution
 * @property string $degree
 * @property string $field
 * @property string $city
 * @property string $country
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string $grade
 * @property string|null $description
 */
#[Fillable(['institution', 'degree', 'field', 'city', 'country', 'starts_on', 'ends_on', 'grade', 'description', 'position'])]
final class Education extends Model
{
    use BelongsToProfile;

    protected $table = 'educations';

    /** The database defaults, so a new entry has them before it is saved. */
    protected $attributes = [
        'position' => 0,
        'field' => '',
        'city' => '',
        'country' => '',
        'grade' => '',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
