<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @extends EntrySection<Project> */
final class Projects extends EntrySection
{
    /** @return HasMany<Project, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->projects();
    }

    protected function blank(): array
    {
        return ['name' => '', 'role' => '', 'url' => '', 'starts_on' => '', 'ends_on' => '', 'description' => '', 'achievements' => ''];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.role' => ['nullable', 'string', 'max:255'],
            'form.url' => ['nullable', 'url:https,http', 'max:500'],
            'form.starts_on' => ['nullable', 'date_format:Y-m'],
            'form.ends_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:form.starts_on'],
            'form.description' => ['nullable', 'string', 'max:2000'],
            'form.achievements' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => __('Project name'),
            'form.role' => __('Your role'),
            'form.url' => __('Link'),
            'form.starts_on' => __('Start'),
            'form.ends_on' => __('End'),
            'form.description' => __('Description'),
            'form.achievements' => __('Achievements'),
        ];
    }

    protected function toForm(Model $entry): array
    {
        /** @var Project $entry */
        return [
            'name' => $entry->name,
            'role' => $entry->role,
            'url' => $entry->url,
            'starts_on' => self::month($entry->starts_on),
            'ends_on' => self::month($entry->ends_on),
            'description' => (string) $entry->description,
            'achievements' => self::text($entry->achievements),
        ];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'name' => $form['name'],
            'role' => self::string($form['role'] ?? null),
            'url' => self::string($form['url'] ?? null),
            'starts_on' => self::date($form['starts_on'] ?? null),
            'ends_on' => self::date($form['ends_on'] ?? null),
            'description' => ($form['description'] ?? '') ?: null,
            'achievements' => self::lines($form['achievements'] ?? ''),
        ];
    }

    protected function view(): string
    {
        return 'livewire.profile.projects';
    }
}
