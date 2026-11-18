<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An email from the candidate's mailbox that matched one of their applications, as text.
 *
 * @property int $id
 * @property int $application_id
 * @property string $message_id
 * @property string $from_address
 * @property string $from_name
 * @property string $subject
 * @property string $body
 * @property string $matched_by thread, sender or subject.
 * @property Carbon $received_at
 * @property Carbon $created_at
 */
#[Fillable(['message_id', 'from_address', 'from_name', 'subject', 'body', 'matched_by', 'received_at'])]
final class ReceivedEmail extends Model
{
    public const null UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
