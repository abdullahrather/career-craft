<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The devices someone is signed in on, with a way to sign out all the others.
 * Sessions are stored in the database, which is what makes them listable.
 */
final class Devices extends Component
{
    public string $password = '';

    public bool $signedOut = false;

    public function signOutOthers(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        if ($user->password === null || ! Hash::check($this->password, $user->password)) {
            throw ValidationException::withMessages(['password' => __('That password is not correct.')]);
        }

        Auth::logoutOtherDevices($this->password);
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', session()->getId())
            ->delete();

        $this->reset('password');
        $this->signedOut = true;
    }

    public function render(): View
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $devices = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'device' => self::describe(is_string($session->user_agent ?? null) ? $session->user_agent : ''),
                'ip' => is_string($session->ip_address ?? null) ? $session->ip_address : '',
                'lastActive' => Carbon::createFromTimestamp(is_numeric($session->last_activity ?? null) ? (int) $session->last_activity : 0),
                'current' => ($session->id ?? null) === session()->getId(),
            ]);

        return view('livewire.settings.devices', [
            'devices' => $devices,
            'hasPassword' => $user->password !== null,
        ]);
    }

    /** "Firefox on Windows" from a user agent. Browser and system names stay as they are in every language. */
    public static function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };
        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return __(':browser on :system', [
            'browser' => $browser ?? __('Unknown browser'),
            'system' => $system ?? __('an unknown system'),
        ]);
    }
}
