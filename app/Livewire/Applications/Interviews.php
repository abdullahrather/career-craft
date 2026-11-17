<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** An application's interviews, each one ready to add to a calendar. */
final class Interviews extends Component
{
    #[Locked]
    public int $applicationId;

    public string $startsAt = '';

    public string $minutes = '60';

    public string $venue = '';

    public function add(): void
    {
        $this->validate([
            'startsAt' => ['required', 'date_format:Y-m-d\TH:i'],
            'minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'venue' => ['nullable', 'string', 'max:500'],
        ], attributes: ['startsAt' => __('Date and time'), 'minutes' => __('Length in minutes'), 'venue' => __('Where')]);

        $application = $this->application();
        $interview = $application->interviews()->create(['starts_at' => $this->startsAt, 'minutes' => (int) $this->minutes, 'location' => trim($this->venue)]);
        $application->log('interview', ['starts_at' => $interview->starts_at->format('Y-m-d H:i')]);
        $this->reset('startsAt', 'minutes', 'venue');
        $this->dispatch('application-changed');
    }

    public function remove(int $id): void
    {
        $this->application()->interviews()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.applications.interviews', ['interviews' => $this->application()->interviews()->get()]);
    }

    private function application(): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($this->applicationId);
    }
}
