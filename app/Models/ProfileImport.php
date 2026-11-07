<?php

declare(strict_types=1);

namespace App\Models;

use App\Import\ProfileDraft;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CV or LinkedIn export that has been read, waiting for the person to confirm what
 * goes into their profile. Deleted once they apply or discard it.
 *
 * @phpstan-import-type Draft from ProfileDraft
 *
 * @property int $id
 * @property int $profile_id
 * @property string $source
 * @property string $filename
 * @property string $status
 * @property string|null $text
 * @property Draft|null $data
 */
#[Fillable(['source', 'filename', 'status', 'text', 'data'])]
final class ProfileImport extends Model
{
    public const string READING = 'reading';

    public const string READY = 'ready';

    public const string FAILED = 'failed';

    /** @return BelongsTo<Profile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
