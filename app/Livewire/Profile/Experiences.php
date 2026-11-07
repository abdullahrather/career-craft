<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Experience;
use App\Models\Profile;
use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/** @extends EntrySection<Experience> */
final class Experiences extends EntrySection
{
    /** @return HasMany<Experience, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->experiences();
    }

    protected function blank(): array
    {
        return ['title' => '', 'employer' => '', 'city' => '', 'country' => '', 'starts_on' => '', 'ends_on' => '', 'current' => false, 'description' => '', 'achievements' => ''];
    }

    protected function rules(): array
    {
        return [
            'form.title' => ['required', 'string', 'max:255'],
            'form.employer' => ['required', 'string', 'max:255'],
            'form.city' => ['nullable', 'string', 'max:255'],
            'form.country' => ['nullable', Rule::in(Countries::codes())],
            'form.starts_on' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'form.current' => ['boolean'],
            'form.ends_on' => ['nullable', 'required_unless:form.current,true', 'date_format:Y-m', 'after_or_equal:form.starts_on'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.achievements' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.title' => __('Job title'),
            'form.employer' => __('Employer'),
            'form.city' => __('City'),
            'form.country' => __('Country'),
            'form.starts_on' => __('Start'),
            'form.ends_on' => __('End'),
            'form.description' => __('Description'),
            'form.achievements' => __('Achievements'),
        ];
    }

    protected function toForm(Model $entry): array
    {
        /** @var Experience $entry */
        return [
            'title' => $entry->title,
            'employer' => $entry->employer,
            'city' => $entry->city,
            'country' => $entry->country,
            'starts_on' => self::month($entry->starts_on),
            'ends_on' => self::month($entry->ends_on),
            'current' => $entry->ends_on === null,
            'description' => (string) $entry->description,
            'achievements' => self::text($entry->achievements),
        ];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'title' => $form['title'],
            'employer' => $form['employer'],
            'city' => self::string($form['city'] ?? null),
            'country' => self::string($form['country'] ?? null),
            'starts_on' => self::date($form['starts_on']),
            'ends_on' => ($form['current'] ?? false) ? null : self::date($form['ends_on'] ?? null),
            'description' => ($form['description'] ?? '') ?: null,
            'achievements' => self::lines($form['achievements'] ?? ''),
        ];
    }

    protected function view(): string
    {
        return 'livewire.profile.experiences';
    }
}
