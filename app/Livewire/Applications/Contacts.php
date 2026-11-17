<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** The people behind an application: recruiters, hiring managers, anyone to follow up with. */
final class Contacts extends Component
{
    #[Locked]
    public int $applicationId;

    public string $name = '';

    public string $role = '';

    public string $email = '';

    public string $phone = '';

    public string $linkedin = '';

    public function add(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9 +()\/.-]+$/'],
            'linkedin' => ['nullable', 'url:http,https', 'max:2048'],
        ], attributes: ['name' => __('Name'), 'role' => __('Role'), 'email' => __('Email'), 'phone' => __('Phone'), 'linkedin' => __('LinkedIn profile')]);

        $application = $this->application();
        $contact = $application->contacts()->create([
            'name' => trim($this->name),
            'role' => trim($this->role),
            'email' => $this->email !== '' ? trim($this->email) : null,
            'phone' => $this->phone !== '' ? trim($this->phone) : null,
            'linkedin' => $this->linkedin !== '' ? $this->linkedin : null,
        ]);
        $application->log('contact', ['name' => $contact->name]);
        $this->reset('name', 'role', 'email', 'phone', 'linkedin');
        $this->dispatch('application-changed');
    }

    public function remove(int $id): void
    {
        $this->application()->contacts()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.applications.contacts', ['contacts' => $this->application()->contacts()->get()]);
    }

    private function application(): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($this->applicationId);
    }
}
