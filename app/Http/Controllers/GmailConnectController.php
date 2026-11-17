<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Applications\EmailSender;
use App\Mail\GmailTransport;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Lets CareerCraft send applications through the candidate's Gmail. Google asks them to allow
 * sending email only (not reading it); the refresh token keeps that working until they take
 * it back. Uses the runner's Google sign-in keys.
 */
final class GmailConnectController
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return $this->google()
            ->scopes([GmailTransport::SCOPE])
            // A refresh token every time, so sending keeps working after the first hour.
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request, EmailSender $sender): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $google = $this->google()->user();
        } catch (Throwable) {
            return $this->failed(__('Connecting Gmail did not work. Please try again.'));
        }
        if (! $google instanceof GoogleUser || ! in_array(GmailTransport::SCOPE, $google->approvedScopes, true) || blank($google->refreshToken) || ! is_string($google->getEmail())) {
            return $this->failed(__('Gmail was connected without permission to send email. Connect again and allow sending.'));
        }

        $account = $user->mailAccount()->updateOrCreate([], [
            'provider' => 'gmail',
            'host' => null,
            'port' => null,
            'security' => null,
            'username' => null,
            'password' => null,
            'access_token' => $google->token,
            'refresh_token' => $google->refreshToken,
            'token_expires_at' => now()->addSeconds((int) $google->expiresIn),
            'from_address' => $google->getEmail(),
            'from_name' => $user->name,
            'verified_at' => null,
        ]);
        $error = $sender->test($account);
        if ($error !== null) {
            return $this->failed($error);
        }
        $account->update(['verified_at' => now()]);

        return redirect()->route('settings')->with('status', __('Gmail is connected. A test email went to :address.', ['address' => $account->from_address]));
    }

    /** Google, coming back to this page rather than to sign-in. */
    private function google(): GoogleProvider
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirectUrl(route('mail.gmail.callback'));
    }

    /** Back to the settings, saying what went wrong. */
    private function failed(string $error): RedirectResponse
    {
        return redirect()->route('settings')->with('error', $error);
    }
}
