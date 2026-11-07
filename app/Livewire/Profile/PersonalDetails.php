<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\Profile;
use App\Models\User;
use App\Support\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/** Headline, summary and contact details at the top of every CV. */
final class PersonalDetails extends Component
{
    public string $headline = '';

    public string $summary = '';

    public string $email = '';

    public string $phone = '';

    public string $city = '';

    public string $country = '';

    public bool $saved = false;

    public function mount(): void
    {
        $profile = $this->profile();
        $this->headline = $profile->headline;
        $this->summary = (string) $profile->summary;
        $this->email = $profile->email;
        $this->phone = $profile->phone;
        $this->city = $profile->city;
        $this->country = $profile->country;
    }

    /** @return array<string, list<mixed>> Also used when an import fills these fields. */
    public static function rules(): array
    {
        return [
            'headline' => ['nullable', 'string', 'max:120'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +()\/.-]*$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', Rule::in(Countries::codes())],
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'headline' => __('Headline'),
            'summary' => __('Summary'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'city' => __('City'),
            'country' => __('Country'),
        ];
    }

    public function save(): void
    {
        $this->validate(self::rules(), attributes: self::labels());

        // The properties are typed strings and have just passed validation.
        $this->profile()->update([
            'headline' => trim($this->headline),
            'summary' => trim($this->summary) ?: null,
            'email' => trim($this->email),
            'phone' => trim($this->phone),
            'city' => trim($this->city),
            'country' => $this->country,
        ]);
        $this->saved = true;
        $this->dispatch('profile-changed');
    }

    public function render(): View
    {
        return view('livewire.profile.personal-details', ['countries' => Countries::options()]);
    }

    private function profile(): Profile
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->careerProfile();
    }
}
