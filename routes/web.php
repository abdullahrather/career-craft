<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SocialLoginController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::post('/locale', LocaleController::class)->name('locale');

// Sign in with Google or GitHub, or connect one from the settings page.
Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    Route::view('/profile', 'profile')->name('profile');

    // Only while another way in remains: a password or another connected account.
    Route::delete('/settings/connections/{provider}', function (string $provider): RedirectResponse {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        if ($user->password === null && $user->socialAccounts()->where('provider', '!=', $provider)->doesntExist()) {
            return back()->with('error', __('Set a password first, so you can still sign in.'));
        }
        $user->socialAccounts()->where('provider', $provider)->delete();

        return back()->with('status', __('Disconnected.'));
    })->name('social.disconnect');
});
