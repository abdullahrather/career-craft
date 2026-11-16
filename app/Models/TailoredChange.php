<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One rewording the AI suggested for a tailored CV: the profile's summary or one bullet
 * point, written for the job. It shows on the CV only once the candidate accepts it, and
 * only while the profile still has the text it rewords.
 *
 * @property int $id
 * @property int $resume_id
 * @property int|null $experience_id
 * @property int $position
 * @property string $original
 * @property string|null $suggestion
 * @property list<array{quote: string, kind: string}> $flags
 * @property bool $checked
 * @property bool $confirmed
 * @property string $status
 * @property string|null $error
 * @property-read Resume $resume
 * @property-read Experience|null $experience
 */
#[Fillable(['experience_id', 'position', 'original', 'suggestion', 'flags', 'checked', 'confirmed', 'status', 'error'])]
final class TailoredChange extends Model
{
    public const string WAITING = 'waiting';

    public const string READY = 'ready';

    /** The AI found nothing to change. */
    public const string UNCHANGED = 'unchanged';

    public const string FAILED = 'failed';

    public const string ACCEPTED = 'accepted';

    public const string REJECTED = 'rejected';

    protected $attributes = ['flags' => '[]'];

    /** @return BelongsTo<Resume, $this> */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** Whether something must be confirmed before it can be accepted: a flag, or a check that couldn't run. */
    public function blocked(): bool
    {
        return ($this->flags !== [] || ! $this->checked) && ! $this->confirmed;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['flags' => 'array', 'checked' => 'boolean', 'confirmed' => 'boolean'];
    }
}
