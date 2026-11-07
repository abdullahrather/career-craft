<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/** The profile's score and next steps, refreshed whenever a section changes. */
final class Completeness extends Component
{
    #[On('profile-changed')]
    public function refresh(): void
    {
        // Re-rendering recalculates the score.
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return view('livewire.profile.completeness', $user->careerProfile()->completeness());
    }
}
