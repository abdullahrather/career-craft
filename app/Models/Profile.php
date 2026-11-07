<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person's career profile: the one record every CV, cover letter and job match is
 * built from. photo_path isn't fillable on purpose: only the photo upload sets it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $headline
 * @property string|null $summary
 * @property string $email
 * @property string $phone
 * @property string $city
 * @property string $country
 * @property string|null $photo_path
 * @property-read ProfileImport|null $import
 */
#[Fillable(['headline', 'summary', 'email', 'phone', 'city', 'country'])]
final class Profile extends Model
{
    /** A summary shorter than this reads as unfinished. */
    public const int SUMMARY_MIN_LENGTH = 80;

    /** The database defaults, so a new profile has them before it is saved. */
    protected $attributes = [
        'headline' => '',
        'email' => '',
        'phone' => '',
        'city' => '',
        'country' => '',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<ProfileImport, $this> */
    public function import(): HasOne
    {
        return $this->hasOne(ProfileImport::class);
    }

    /** @return HasMany<Experience, $this> */
    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class)->orderBy('position');
    }

    /** @return HasMany<Education, $this> */
    public function educations(): HasMany
    {
        return $this->hasMany(Education::class)->orderBy('position');
    }

    /** @return HasMany<Skill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class)->orderBy('position');
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position');
    }

    /** @return HasMany<Certification, $this> */
    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class)->orderBy('position');
    }

    /** @return HasMany<ProfileLanguage, $this> */
    public function languages(): HasMany
    {
        return $this->hasMany(ProfileLanguage::class)->orderBy('position');
    }

    /** @return HasMany<ProfileLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(ProfileLink::class)->orderBy('position');
    }

    /**
     * How complete the profile is, out of 100, and what to do next. Weighted towards
     * what employers and applicant tracking systems look at first.
     *
     * @return array{score: int, next: list<array{section: string, text: string}>}
     */
    public function completeness(): array
    {
        $experiences = $this->experiences()->get();
        $checks = [
            ['personal', 10, $this->headline !== '', __('Add a headline, such as the role you want.')],
            ['personal', 15, mb_strlen((string) $this->summary) >= self::SUMMARY_MIN_LENGTH, __('Write a short summary of what you do and what you want next.')],
            ['personal', 10, ($this->email !== '' || $this->phone !== '') && $this->city !== '', __('Add how to reach you and where you are based.')],
            ['experience', 20, $experiences->isNotEmpty(), __('Add your work experience.')],
            ['experience', 10, $experiences->isNotEmpty() && $experiences->every(fn (Experience $job): bool => $job->achievements !== []), __('List at least one achievement for every job.')],
            ['education', 10, $this->educations()->exists(), __('Add your education.')],
            ['skills', 10, $this->skills()->count() >= 5, __('List at least five skills.')],
            ['languages', 10, $this->languages()->exists(), __('Add the languages you speak.')],
            ['links', 5, $this->links()->exists(), __('Add a link, such as LinkedIn or a portfolio.')],
        ];

        $score = 0;
        $next = [];
        foreach ($checks as [$section, $weight, $done, $text]) {
            if ($done) {
                $score += $weight;
            } else {
                $next[] = ['section' => $section, 'text' => $text];
            }
        }

        return ['score' => $score, 'next' => $next];
    }
}
