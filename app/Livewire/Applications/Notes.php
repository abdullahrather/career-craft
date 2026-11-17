<?php

declare(strict_types=1);

namespace App\Livewire\Applications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Notes on an application, newest first: what was said, what to ask, what to remember. */
final class Notes extends Component
{
    #[Locked]
    public int $applicationId;

    public string $body = '';

    public function add(): void
    {
        $this->body = trim($this->body);
        $this->validate(['body' => ['required', 'string', 'max:5000']], attributes: ['body' => __('Note')]);

        $application = $this->application();
        $application->notes()->create(['body' => $this->body]);
        $application->log('note');
        $this->reset('body');
        $this->dispatch('application-changed');
    }

    public function remove(int $id): void
    {
        $this->application()->notes()->whereKey($id)->delete();
    }

    public function render(): View
    {
        return view('livewire.applications.notes', ['notes' => $this->application()->notes()->get()]);
    }

    private function application(): Application
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user->applications()->findOrFail($this->applicationId);
    }
}
