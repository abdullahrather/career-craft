<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Inbox\GmailReader;
use App\Inbox\MailReader;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Lets CareerCraft read the candidate's Gmail inbox for employers' replies. Google asks them
 * to allow reading only (not sending, changing or deleting); the refresh token keeps that
 * working until they take it back. Uses the runner's Google sign-in keys.
 */
final class GmailMailboxController
{
    public function redirect(): SymfonyRedirect
    {
        abort_unless(GmailConnectController::enabled(), 404);

        return $this->google()
            ->scopes([GmailReader::SCOPE])
            // A refresh token every time, so checking keeps working after the first hour.
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request, MailReader $reader): RedirectResponse
    {
        abort_unless(GmailConnectController::enabled(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $google = $this->google()->user();
        } catch (Throwable) {
            return $this->failed(__('Connecting Gmail did not work. Please try again.'));
        }
        if (! $google instanceof GoogleUser || ! in_array(GmailReader::SCOPE, $google->approvedScopes, true) || blank($google->refreshToken) || ! is_string($google->getEmail())) {
            return $this->failed(__('Gmail was connected without permission to read email. Connect again and allow reading.'));
        }

        // Tested before it's saved: a Gmail that can't be read leaves the mailbox there was.
        $gmail = [
            'provider' => 'gmail',
            'host' => null,
            'port' => null,
            'security' => null,
            'username' => $google->getEmail(),
            'password' => null,
            'access_token' => $google->token,
            'refresh_token' => $google->refreshToken,
            'token_expires_at' => now()->addSeconds((int) $google->expiresIn),
        ];
        $error = $reader->test($user->mailbox()->make($gmail));
        if ($error !== null) {
            return $this->failed($error);
        }
        // Another account starts afresh; the same one carries on where it was.
        $mailbox = $user->mailbox;
        $same = $mailbox !== null && $mailbox->provider === 'gmail' && $mailbox->username === $gmail['username'];
        $user->mailbox()->updateOrCreate([], [...$gmail, 'error' => null, 'login_failed' => false, ...($same ? [] : ['last_uid' => null, 'checked_at' => null])]);

        return redirect()->route('settings')->with('status', __('Gmail is connected for replies: :address. CareerCraft checks it every 15 minutes.', ['address' => $gmail['username']]));
    }

    /** Google, coming back to this page rather than to sign-in. */
    private function google(): GoogleProvider
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirectUrl(route('mailbox.gmail.callback'));
    }

    /** Back to the settings, saying what went wrong. */
    private function failed(string $error): RedirectResponse
    {
        return redirect()->route('settings')->with('error', $error);
    }
}
