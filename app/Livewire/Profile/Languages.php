<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\ProfileLanguage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;

/** @extends EntrySection<ProfileLanguage> */
final class Languages extends EntrySection
{
    /** @return array<string, string> Levels of the Common European Framework of Reference, plus native. */
    public static function levels(): array
    {
        return [
            'native' => __('Native speaker'),
            'C2' => __('C2 · Proficient'),
            'C1' => __('C1 · Advanced'),
            'B2' => __('B2 · Upper intermediate'),
            'B1' => __('B1 · Intermediate'),
            'A2' => __('A2 · Elementary'),
            'A1' => __('A1 · Beginner'),
        ];
    }

    /** @return HasMany<ProfileLanguage, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->languages();
    }

    protected function blank(): array
    {
        return ['name' => '', 'level' => 'B2'];
    }

    protected function rules(): array
    {
        return [
            'form.name' => [
                'required', 'string', 'max:60',
                Rule::unique('profile_languages', 'name')->where('profile_id', $this->profile()->id)->ignore($this->editing),
            ],
            'form.level' => ['required', Rule::in(array_keys(self::levels()))],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.name' => __('Language'), 'form.level' => __('Level')];
    }

    protected function toForm(Model $entry): array
    {
        /** @var ProfileLanguage $entry */
        return ['name' => $entry->name, 'level' => $entry->level];
    }

    protected function toAttributes(array $form): array
    {
        return ['name' => $form['name'], 'level' => $form['level']];
    }

    protected function view(): string
    {
        return 'livewire.profile.languages';
    }
}
