<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Inbox\MailReader;
use App\Inbox\OutlookReader;
use App\Mail\OutlookTransport;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lets CareerCraft read the candidate's Outlook inbox (personal or work) for employers'
 * replies. Microsoft asks them to allow reading only (not sending, changing or deleting); the
 * refresh token keeps that working until they take it back. Uses the runner's Entra app keys.
 */
final class OutlookMailboxController
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(OutlookConnectController::enabled(), 404);
        // Checked on the way back, so a sign-in someone else started can't be slipped in.
        $state = Str::random(40);
        $request->session()->put('outlook_mailbox_state', $state);

        return redirect()->away(OutlookTransport::endpoint('authorize').'?'.http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => route('mailbox.outlook.callback'),
            'response_mode' => 'query',
            'scope' => OutlookReader::SCOPES,
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request, MailReader $reader): RedirectResponse
    {
        abort_unless(OutlookConnectController::enabled(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $state = $request->session()->pull('outlook_mailbox_state');
        if (! is_string($state) || ! hash_equals($state, $request->string('state')->toString()) || ! $request->filled('code')) {
            return $this->failed(__('Connecting Outlook did not work. Please try again.'));
        }

        try {
            $tokens = OutlookTransport::token(['grant_type' => 'authorization_code', 'code' => $request->string('code')->toString(), 'redirect_uri' => route('mailbox.outlook.callback'), 'scope' => OutlookReader::SCOPES]);
            $access = $tokens->json('access_token');
            $me = is_string($access) ? Http::withToken($access)->timeout(15)->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'mail,userPrincipalName']) : null;
        } catch (ConnectionException) {
            return $this->failed(__('Connecting Outlook did not work. Please try again.'));
        }
        $refresh = $tokens->json('refresh_token');
        $scope = $tokens->json('scope');
        $address = $me?->json('mail') ?? $me?->json('userPrincipalName');
        if (! is_string($access) || ! is_string($refresh) || ! is_string($address) || ! is_string($scope) || ! OutlookReader::canRead($scope)) {
            return $this->failed(__('Outlook was connected without permission to read email. Connect again and allow reading.'));
        }

        // Tested before it's saved: an Outlook that can't be read leaves the mailbox there was.
        $expires = $tokens->json('expires_in');
        $outlook = [
            'provider' => 'outlook',
            'host' => null,
            'port' => null,
            'security' => null,
            'username' => Str::lower($address),
            'password' => null,
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600),
        ];
        $error = $reader->test($user->mailbox()->make($outlook));
        if ($error !== null) {
            return $this->failed($error);
        }
        // Another account starts afresh; the same one carries on where it was.
        $mailbox = $user->mailbox;
        $same = $mailbox !== null && $mailbox->provider === 'outlook' && $mailbox->username === $outlook['username'];
        $user->mailbox()->updateOrCreate([], [...$outlook, 'error' => null, 'login_failed' => false, ...($same ? [] : ['last_uid' => null, 'checked_at' => null])]);

        return redirect()->route('settings')->with('status', __('Outlook is connected for replies: :address. CareerCraft checks it every 15 minutes.', ['address' => $outlook['username']]));
    }

    /** Back to the settings, saying what went wrong. */
    private function failed(string $error): RedirectResponse
    {
        return redirect()->route('settings')->with('error', $error);
    }
}
