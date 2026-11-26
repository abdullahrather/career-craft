<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property string|null $kind One of KINDS, or null until the AI has read it.
 * @property Carbon $received_at
 * @property Carbon $created_at
 * @property-read Application $application
 */
#[Fillable(['message_id', 'from_address', 'from_name', 'subject', 'body', 'matched_by', 'received_at', 'kind'])]
final class ReceivedEmail extends Model
{
    public const null UPDATED_AT = null;

    /** What a reply can be. "other" is anything else, such as a note that the application arrived. */
    public const array KINDS = ['rejection', 'interview', 'assessment', 'information', 'offer', 'other'];

    /** @return array<string, string> */
    public static function kindNames(): array
    {
        return ['rejection' => __('Rejection'), 'interview' => __('Interview invite'), 'assessment' => __('Assessment'), 'information' => __('Asks for information'), 'offer' => __('Offer'), 'other' => __('Other')];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }
}
