<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A cover letter for a job post. The AI writes the subject and the body; the greeting,
 * closing and layout come from the letter's language.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $job_post_id
 * @property int|null $resume_id
 * @property string $language
 * @property string $tone
 * @property string $length
 * @property string $recipient
 * @property string $contact
 * @property string $subject
 * @property string|null $body
 * @property list<array{quote: string, kind: string}> $flags
 * @property list<string> $confirmed
 * @property bool $checked
 * @property string $status
 * @property string|null $error
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read JobPost|null $jobPost
 * @property-read Resume|null $resume
 */
#[Fillable(['job_post_id', 'resume_id', 'language', 'tone', 'length', 'recipient', 'contact', 'subject', 'body', 'flags', 'confirmed', 'checked', 'status', 'error'])]
final class CoverLetter extends Model
{
    public const string WRITING = 'writing';

    public const string READY = 'ready';

    public const string FAILED = 'failed';

    public const array TONES = ['formal', 'warm', 'confident'];

    public const array LENGTHS = ['short', 'standard'];

    /** In the confirmed list: the candidate confirmed a letter the fact guard couldn't check. */
    public const string UNCHECKED = '*';

    protected $attributes = ['flags' => '[]', 'confirmed' => '[]', 'contact' => '', 'subject' => ''];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<JobPost, $this> */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    /** @return BelongsTo<Resume, $this> */
    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    /**
     * The flags still open: not confirmed, and still in the text. Taking a flagged phrase
     * out of the letter settles it as well as confirming it does.
     *
     * @return list<array{quote: string, kind: string}>
     */
    public function openFlags(): array
    {
        $text = mb_strtolower($this->subject."\n".$this->body);

        return array_values(array_filter($this->flags, fn (array $flag): bool => ! in_array($flag['quote'], $this->confirmed, true) && str_contains($text, mb_strtolower($flag['quote']))));
    }

    /** Whether it can be downloaded: written, and nothing flagged left to confirm, or a check that couldn't run. */
    public function sendable(): bool
    {
        return $this->status === self::READY && $this->openFlags() === [] && ($this->checked || in_array(self::UNCHECKED, $this->confirmed, true));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['flags' => 'array', 'confirmed' => 'array', 'checked' => 'boolean'];
    }
}
