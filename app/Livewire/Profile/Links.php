<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\ProfileLink;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @extends EntrySection<ProfileLink> */
final class Links extends EntrySection
{
    /** @return HasMany<ProfileLink, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->links();
    }

    protected function blank(): array
    {
        return ['label' => '', 'url' => ''];
    }

    protected function rules(): array
    {
        return [
            'form.label' => ['required', 'string', 'max:60'],
            'form.url' => ['required', 'url:https,http', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['form.label' => __('Label'), 'form.url' => __('Address')];
    }

    protected function toForm(Model $entry): array
    {
        /** @var ProfileLink $entry */
        return ['label' => $entry->label, 'url' => $entry->url];
    }

    protected function toAttributes(array $form): array
    {
        return ['label' => $form['label'], 'url' => $form['url']];
    }

    protected function view(): string
    {
        return 'livewire.profile.links';
    }
}
