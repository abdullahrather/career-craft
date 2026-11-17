<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job post the person pasted or linked, and what was read from it: the role, company,
 * location, work mode, seniority, language, and the required and nice-to-have skills.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $url
 * @property string $status
 * @property string|null $error
 * @property string|null $text
 * @property string $title
 * @property string $company
 * @property string $location
 * @property string $work_mode
 * @property string $seniority
 * @property string $language
 * @property list<string> $required_skills
 * @property list<string> $nice_skills
 * @property-read User $user
 */
#[Fillable(['url', 'status', 'error', 'text', 'title', 'company', 'location', 'work_mode', 'seniority', 'language', 'required_skills', 'nice_skills'])]
final class JobPost extends Model
{
    public const string READING = 'reading';

    public const string READY = 'ready';

    public const string FAILED = 'failed';

    public const array WORK_MODES = ['onsite', 'hybrid', 'remote'];

    public const array SENIORITIES = ['intern', 'junior', 'mid', 'senior', 'lead'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => self::READING,
        'title' => '',
        'company' => '',
        'location' => '',
        'work_mode' => '',
        'seniority' => '',
        'language' => 'en',
        'required_skills' => '[]',
        'nice_skills' => '[]',
    ];

    /**
     * A post to analyse from a job found on Find jobs: the listing's own text when it has
     * the whole post, otherwise its page is read.
     */
    public static function fromListing(User $user, JobListing $listing): self
    {
        $url = $listing->sources->first()?->url;
        if (mb_strlen($listing->description) < 200) {
            return $user->jobPosts()->create(['url' => $url]);
        }
        $text = trim(implode("\n", array_filter([$listing->title, implode(' · ', array_filter([$listing->company, $listing->location]))]))."\n\n".$listing->description);

        return $user->jobPosts()->create(['text' => $text, 'url' => $url]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['required_skills' => 'array', 'nice_skills' => 'array'];
    }
}
