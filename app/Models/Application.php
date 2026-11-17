<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job the person tracks on the board, with the CV and letter that went out for it.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $job_post_id
 * @property string $title
 * @property string $company
 * @property string $location
 * @property string|null $url
 * @property string $source
 * @property string $status
 * @property int $position
 * @property Carbon|null $applied_on
 * @property int|null $resume_version_id
 * @property string|null $cv_name
 * @property string|null $cv_path
 * @property int|null $cover_letter_id
 * @property string|null $letter_name
 * @property string|null $letter_path
 * @property Carbon|null $nudged_at
 * @property-read JobPost|null $jobPost
 * @property-read User $user
 * @property-read Collection<int, ApplicationContact> $contacts
 * @property-read Collection<int, ApplicationNote> $notes
 * @property-read Collection<int, ApplicationEvent> $events
 */
#[Fillable(['job_post_id', 'title', 'company', 'location', 'url', 'source', 'status', 'position', 'applied_on', 'resume_version_id', 'cv_name', 'cv_path', 'cover_letter_id', 'letter_name', 'letter_path'])]
final class Application extends Model
{
    public const array STATUSES = ['saved', 'applied', 'interviewing', 'offer', 'rejected'];

    /**
     * The board's columns, in order, with their names.
     *
     * @return array<string, string>
     */
    public static function statusNames(): array
    {
        return ['saved' => __('Saved'), 'applied' => __('Applied'), 'interviewing' => __('Interviewing'), 'offer' => __('Offer'), 'rejected' => __('Rejected')];
    }

    /**
     * Starts tracking a job, at the end of the Saved column.
     *
     * @param  array{title: string, company?: string, location?: string, url?: string|null, source?: string, job_post_id?: int}  $values
     */
    public static function track(User $user, array $values): self
    {
        $last = $user->applications()->where('status', 'saved')->max('position');
        $position = is_numeric($last) ? (int) $last + 1 : 0;

        $application = $user->applications()->create([...$values, 'status' => 'saved', 'position' => $position]);
        $application->log('added', ['source' => $values['source'] ?? '']);

        return $application;
    }

    /**
     * A job page's address in one form, so the page an application was tracked from and the
     * page its form is on match: no "www.", query, fragment, trailing slash or "/apply".
     */
    public static function normaliseUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || ! isset($parts['host']) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }
        $host = (string) preg_replace('/^www\./', '', strtolower($parts['host']));
        $path = (string) preg_replace('#/apply/?$#', '', rtrim($parts['path'] ?? '', '/'));

        return $host.rtrim($path, '/');
    }

    /** Where an application's document copies are kept, so deleting it can remove them. */
    public static function directory(int $id): string
    {
        return "applications/{$id}";
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<ApplicationEmail, $this> Newest first. */
    public function emails(): HasMany
    {
        return $this->hasMany(ApplicationEmail::class)->latest('id');
    }

    /** @return HasMany<ApplicationContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(ApplicationContact::class)->orderBy('id');
    }

    /** @return HasMany<ApplicationTask, $this> Open ones first, then by day. */
    public function tasks(): HasMany
    {
        return $this->hasMany(ApplicationTask::class)->orderByRaw('done_at is not null')->orderBy('due_on')->orderBy('id');
    }

    /** @return HasMany<ApplicationInterview, $this> In time order. */
    public function interviews(): HasMany
    {
        return $this->hasMany(ApplicationInterview::class)->orderBy('starts_at');
    }

    /** @return HasMany<ApplicationNote, $this> Newest first. */
    public function notes(): HasMany
    {
        return $this->hasMany(ApplicationNote::class)->latest('id');
    }

    /** @return HasMany<ApplicationEvent, $this> Newest first. */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->latest('id');
    }

    /**
     * Adds an event to the timeline.
     *
     * @param  array<string, string|null>  $data
     */
    public function log(string $type, array $data = []): void
    {
        $this->events()->create(['type' => $type, 'data' => $data]);
    }

    /** @return BelongsTo<JobPost, $this> */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class);
    }

    /**
     * Moves the application to a column, at a place in it (the end when none is given). The
     * first move to Applied dates the application today, unless it has a date.
     */
    public function moveTo(string $status, ?int $position = null): void
    {
        $column = self::query()->where('user_id', $this->user_id)->where('status', $status)->whereKeyNot($this->id)->orderBy('position')->orderBy('id')->pluck('id')->all();
        array_splice($column, max(0, min($position ?? count($column), count($column))), 0, [$this->id]);

        $from = $this->status;
        $this->status = $status;
        if ($status === 'applied' && $this->applied_on === null) {
            $this->applied_on = today();
        }
        $this->save();
        foreach ($column as $place => $id) {
            self::query()->whereKey($id)->update(['position' => $place]);
        }
        if ($from !== $status) {
            $this->log('status', ['from' => $from, 'to' => $status]);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['applied_on' => 'date', 'position' => 'integer', 'nudged_at' => 'datetime'];
    }
}
