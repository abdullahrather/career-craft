<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $two_factor_confirmed_at
 * @property int $follow_up_days How many quiet days in Applied before a follow-up is suggested.
 * @property bool $reminder_emails
 * @property int|null $apply_resume_id The CV the apply queue tailors from.
 * @property string $apply_tone The tone of the apply queue's letters.
 * @property bool $is_admin Not fillable: set with `php artisan careercraft:admin`.
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
final class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /** The database default, so a new account has it before it is saved and reloaded. */
    protected $attributes = [
        'is_admin' => false,
        'follow_up_days' => 7,
        'reminder_emails' => true,
        'apply_tone' => 'formal',
    ];

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<Resume, $this> */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class)->latest('updated_at');
    }

    /** @return HasMany<CoverLetter, $this> Newest first. */
    public function coverLetters(): HasMany
    {
        return $this->hasMany(CoverLetter::class)->latest('id');
    }

    /** @return HasMany<JobPost, $this> */
    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class)->latest('id');
    }

    /** @return HasMany<FollowedBoard, $this> */
    public function followedBoards(): HasMany
    {
        return $this->hasMany(FollowedBoard::class)->orderBy('name');
    }

    /** @return HasMany<DismissedListing, $this> */
    public function dismissedListings(): HasMany
    {
        return $this->hasMany(DismissedListing::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** @return HasMany<ApplyQueueItem, $this> Newest first. */
    public function applyQueueItems(): HasMany
    {
        return $this->hasMany(ApplyQueueItem::class)->latest('id');
    }

    /** @return HasMany<SavedSearch, $this> */
    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class)->latest('id');
    }

    /** @return HasOne<Profile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /** The career profile, created empty on first use with the account's email. */
    public function careerProfile(): Profile
    {
        return $this->profile()->firstOrCreate([], ['email' => $this->email]);
    }

    /** Emails and other notifications go out in the person's language. */
    public function preferredLocale(): string
    {
        return $this->locale;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'follow_up_days' => 'integer',
            'reminder_emails' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
