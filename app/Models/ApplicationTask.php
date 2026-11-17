<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Something to do for an application by a day, such as sending a thank-you note.
 *
 * @property int $id
 * @property int $application_id
 * @property string $title
 * @property Carbon $due_on
 * @property Carbon|null $done_at
 * @property-read Application $application
 */
#[Fillable(['title', 'due_on', 'done_at'])]
final class ApplicationTask extends Model
{
    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['due_on' => 'date', 'done_at' => 'datetime'];
    }
}
