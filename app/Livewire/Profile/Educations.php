<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Education;
use App\Models\Profile;
use App\Support\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/** @extends EntrySection<Education> */
final class Educations extends EntrySection
{
    /** @return HasMany<Education, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->educations();
    }

    protected function blank(): array
    {
        return ['institution' => '', 'degree' => '', 'field' => '', 'city' => '', 'country' => '', 'starts_on' => '', 'ends_on' => '', 'grade' => '', 'description' => ''];
    }

    protected function rules(): array
    {
        return [
            'form.institution' => ['required', 'string', 'max:255'],
            'form.degree' => ['required', 'string', 'max:255'],
            'form.field' => ['nullable', 'string', 'max:255'],
            'form.city' => ['nullable', 'string', 'max:255'],
            'form.country' => ['nullable', Rule::in(Countries::codes())],
            'form.starts_on' => ['required', 'date_format:Y-m'],
            // Empty while still studying. An expected end date in the future is fine.
            'form.ends_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:form.starts_on'],
            'form.grade' => ['nullable', 'string', 'max:40'],
            'form.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.institution' => __('School or university'),
            'form.degree' => __('Degree or qualification'),
            'form.field' => __('Field of study'),
            'form.city' => __('City'),
            'form.country' => __('Country'),
            'form.starts_on' => __('Start'),
            'form.ends_on' => __('End'),
            'form.grade' => __('Grade'),
            'form.description' => __('Description'),
        ];
    }

    protected function toForm(Model $entry): array
    {
        /** @var Education $entry */
        return [
            'institution' => $entry->institution,
            'degree' => $entry->degree,
            'field' => $entry->field,
            'city' => $entry->city,
            'country' => $entry->country,
            'starts_on' => self::month($entry->starts_on),
            'ends_on' => self::month($entry->ends_on),
            'grade' => $entry->grade,
            'description' => (string) $entry->description,
        ];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'institution' => $form['institution'],
            'degree' => $form['degree'],
            'field' => self::string($form['field'] ?? null),
            'city' => self::string($form['city'] ?? null),
            'country' => self::string($form['country'] ?? null),
            'starts_on' => self::date($form['starts_on']),
            'ends_on' => self::date($form['ends_on'] ?? null),
            'grade' => self::string($form['grade'] ?? null),
            'description' => ($form['description'] ?? '') ?: null,
        ];
    }

    protected function view(): string
    {
        return 'livewire.profile.educations';
    }
}
