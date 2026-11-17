<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** How the person is reminded: when a quiet application needs a follow-up, and the daily email. */
final class Reminders extends Component
{
    public string $followUpDays = '7';

    public bool $reminderEmails = true;

    public function mount(): void
    {
        $user = $this->user();
        $this->followUpDays = (string) $user->follow_up_days;
        $this->reminderEmails = $user->reminder_emails;
    }

    public function save(): void
    {
        $this->validate(['followUpDays' => ['required', 'integer', 'min:1', 'max:60']], attributes: ['followUpDays' => __('Days before a follow-up')]);
        $this->user()->forceFill(['follow_up_days' => (int) $this->followUpDays, 'reminder_emails' => $this->reminderEmails])->save();
        session()->flash('reminders-saved', __('Saved.'));
    }

    public function render(): View
    {
        return view('livewire.settings.reminders');
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
