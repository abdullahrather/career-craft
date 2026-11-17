<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Applications\EmailSender;
use App\Mail\OutlookTransport;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Lets CareerCraft send applications through the candidate's Outlook (personal or work),
 * with Microsoft's sign-in: they allow sending email only, and the refresh token keeps that
 * working until they take it back. Uses the runner's own Microsoft Entra app keys.
 */
final class OutlookConnectController
{
    public static function enabled(): bool
    {
        return filled(config('services.microsoft.client_id')) && filled(config('services.microsoft.client_secret'));
    }

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        // Checked on the way back, so a sign-in someone else started can't be slipped in.
        $state = Str::random(40);
        $request->session()->put('outlook_state', $state);

        return redirect()->away(OutlookTransport::endpoint('authorize').'?'.http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => route('mail.outlook.callback'),
            'response_mode' => 'query',
            'scope' => OutlookTransport::SCOPES,
            'state' => $state,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request, EmailSender $sender): RedirectResponse
    {
        abort_unless(self::enabled(), 404);
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $state = $request->session()->pull('outlook_state');
        if (! is_string($state) || ! hash_equals($state, $request->string('state')->toString()) || ! $request->filled('code')) {
            return $this->failed(__('Connecting Outlook did not work. Please try again.'));
        }

        try {
            $tokens = OutlookTransport::token(['grant_type' => 'authorization_code', 'code' => $request->string('code')->toString(), 'redirect_uri' => route('mail.outlook.callback')]);
            $access = $tokens->json('access_token');
            $me = is_string($access) ? Http::withToken($access)->timeout(15)->get('https://graph.microsoft.com/v1.0/me', ['$select' => 'mail,userPrincipalName']) : null;
        } catch (ConnectionException) {
            return $this->failed(__('Connecting Outlook did not work. Please try again.'));
        }
        $refresh = $tokens->json('refresh_token');
        $scope = $tokens->json('scope');
        $address = $me?->json('mail') ?? $me?->json('userPrincipalName');
        if (! is_string($access) || ! is_string($refresh) || ! is_string($address) || ! is_string($scope) || ! Str::contains($scope, 'Mail.Send', ignoreCase: true)) {
            return $this->failed(__('Outlook was connected without permission to send email. Connect again and allow sending.'));
        }

        // Tested before it's saved: an Outlook that can't send leaves the account there was.
        $expires = $tokens->json('expires_in');
        $outlook = [
            'provider' => 'outlook',
            'host' => null,
            'port' => null,
            'security' => null,
            'username' => null,
            'password' => null,
            'access_token' => $access,
            'refresh_token' => $refresh,
            'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600),
            'from_address' => $address,
            'from_name' => $user->name,
        ];
        $error = $sender->test($user->mailAccount()->make($outlook));
        if ($error !== null) {
            return $this->failed($error);
        }
        $account = $user->mailAccount()->updateOrCreate([], [...$outlook, 'verified_at' => now()]);

        return redirect()->route('settings')->with('status', __('Outlook is connected. A test email went to :address.', ['address' => $account->from_address]));
    }

    /** Back to the settings, saying what went wrong. */
    private function failed(string $error): RedirectResponse
    {
        return redirect()->route('settings')->with('error', $error);
    }
}
