<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property-read JobPost|null $jobPost
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

        return $user->applications()->create([...$values, 'status' => 'saved', 'position' => $position]);
    }

    /** Where an application's document copies are kept, so deleting it can remove them. */
    public static function directory(int $id): string
    {
        return "applications/{$id}";
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

        $this->status = $status;
        if ($status === 'applied' && $this->applied_on === null) {
            $this->applied_on = today();
        }
        $this->save();
        foreach ($column as $place => $id) {
            self::query()->whereKey($id)->update(['position' => $place]);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['applied_on' => 'date', 'position' => 'integer'];
    }
}
