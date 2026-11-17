<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An application email as it was sent, for the log.
 *
 * @property int $id
 * @property int $application_id
 * @property string $to
 * @property string $subject
 * @property string $body
 * @property list<string> $attachments
 * @property Carbon $created_at
 */
#[Fillable(['to', 'subject', 'body', 'attachments'])]
final class ApplicationEmail extends Model
{
    public const null UPDATED_AT = null;

    /** How many application emails one person can send a day: a slip shouldn't reach dozens of employers. */
    public const int DAILY = 20;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }
}
