<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Tokens for the browser extension, which fills application forms from CareerCraft. A token
 * can only read what the extension needs, is shown once, and can be taken back at any time.
 */
final class Extension extends Component
{
    /** A token just made, shown until the page is left. */
    public ?string $token = null;

    public function create(): void
    {
        $this->token = $this->user()->createToken(__('Browser extension'), ['extension'])->plainTextToken;
    }

    public function revoke(int $id): void
    {
        $this->user()->tokens()->whereKey($id)->delete();
        $this->token = null;
    }

    public function render(): View
    {
        return view('livewire.settings.extension', ['tokens' => $this->user()->tokens()->latest('id')->get()]);
    }

    private function user(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
