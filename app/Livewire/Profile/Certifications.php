<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Certification;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @extends EntrySection<Certification> */
final class Certifications extends EntrySection
{
    /** @return HasMany<Certification, Profile> */
    protected function entries(Profile $profile): HasMany
    {
        return $profile->certifications();
    }

    protected function blank(): array
    {
        return ['name' => '', 'issuer' => '', 'issued_on' => '', 'expires_on' => '', 'url' => ''];
    }

    protected function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:255'],
            'form.issuer' => ['nullable', 'string', 'max:255'],
            'form.issued_on' => ['nullable', 'date_format:Y-m'],
            'form.expires_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:form.issued_on'],
            'form.url' => ['nullable', 'url:https,http', 'max:500'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'form.name' => __('Certificate'),
            'form.issuer' => __('Issued by'),
            'form.issued_on' => __('Issued'),
            'form.expires_on' => __('Expires'),
            'form.url' => __('Link to the credential'),
        ];
    }

    protected function toForm(Model $entry): array
    {
        /** @var Certification $entry */
        return [
            'name' => $entry->name,
            'issuer' => $entry->issuer,
            'issued_on' => self::month($entry->issued_on),
            'expires_on' => self::month($entry->expires_on),
            'url' => $entry->url,
        ];
    }

    protected function toAttributes(array $form): array
    {
        return [
            'name' => $form['name'],
            'issuer' => self::string($form['issuer'] ?? null),
            'issued_on' => self::date($form['issued_on'] ?? null),
            'expires_on' => self::date($form['expires_on'] ?? null),
            'url' => self::string($form['url'] ?? null),
        ];
    }

    protected function view(): string
    {
        return 'livewire.profile.certifications';
    }
}
