<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/** @extends EntrySection<Skill> */
final class Skills extends EntrySection
{
    /** @return array<int, string> */
    public static function levels(): array
    {
        return [1 => __('Basic'), 2 => __('Good'), 3 => __('Very good'), 4 => __('Expert')];
    }

    /** @return HasMany<Skill, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->skills();
    }

    protected function blank(): array
    {
        return ['name' => '', 'level' => 2];
    }

    protected function rules(): array
    {
        return [
            'form.name' => [
                'required', 'string', 'max:80',
                Rule::unique('skills', 'name')->where('profile_id', $this->profile()->id)->ignore($this->editing),
            ],
            'form.level' => ['required', 'integer', Rule::in(array_keys(self::levels()))],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.name' => __('Skill'), 'form.level' => __('Level')];
    }

    protected function toForm(Model $entry): array
    {
        /** @var Skill $entry */
        return ['name' => $entry->name, 'level' => $entry->level];
    }

    protected function toAttributes(array $form): array
    {
        return ['name' => $form['name'], 'level' => is_numeric($form['level']) ? (int) $form['level'] : 2];
    }

    protected function view(): string
    {
        return 'livewire.profile.skills';
    }
}
