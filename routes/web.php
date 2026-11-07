<?php

declare(strict_types=1);

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\SocialLoginController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

Route::view('/', 'home')->name('home');

Route::post('/locale', LocaleController::class)->name('locale');

// Sign in with Google or GitHub, or connect one from the settings page.
Route::get('/auth/{provider}/redirect', [SocialLoginController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [SocialLoginController::class, 'callback'])->name('social.callback');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::view('/settings', 'settings')->name('settings');
    Route::view('/profile', 'profile')->name('profile');
    Route::view('/profile/import', 'profile-import')->name('profile.import');

    // The CV photo is private: only its owner sees it.
    Route::get('/profile/photo', function (): StreamedResponse {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        $path = $user->careerProfile()->photo_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
    })->name('profile.photo');

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
