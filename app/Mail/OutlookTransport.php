<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends through Microsoft Graph with the candidate's permission (Mail.Send only), refreshing
 * the access token when it has run out. Microsoft may hand out a new refresh token each time;
 * the newest is kept.
 */
final class OutlookTransport extends AbstractTransport
{
    /** Sending as the person, staying signed in for it, and reading their address. */
    public const string SCOPES = 'offline_access https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read';

    public function __construct(private MailAccount $account)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'outlook';
    }

    /** The URL of a Microsoft identity platform endpoint, for the configured tenant. */
    public static function endpoint(string $path): string
    {
        return 'https://login.microsoftonline.com/'.rawurlencode(config()->string('services.microsoft.tenant', 'common')).'/oauth2/v2.0/'.$path;
    }

    /**
     * Asks Microsoft for tokens: for a sign-in's code, or with a refresh token.
     *
     * @param  array<string, string|null>  $grant
     */
    public static function token(array $grant): Response
    {
        return Http::asForm()->timeout(15)->post(self::endpoint('token'), [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'scope' => self::SCOPES,
            ...$grant,
        ]);
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            // Graph takes the whole MIME message, base64 encoded, as plain text.
            $response = Http::withToken($this->accessToken())->timeout(20)
                ->withBody(base64_encode($message->toString()), 'text/plain')
                ->post('https://graph.microsoft.com/v1.0/me/sendMail');
        } catch (ConnectionException) {
            throw new TransportException(__('Outlook didn\'t answer. Try again in a moment.'));
        }
        if ($response->failed()) {
            $reason = $response->json('error.message');
            throw new TransportException(__('Outlook refused the email: :reason', ['reason' => is_string($reason) ? $reason : (string) $response->status()]));
        }
    }

    /** A valid access token, refreshed with the refresh token when needed. */
    private function accessToken(): string
    {
        $account = $this->account;
        if ($account->access_token !== null && $account->token_expires_at?->isAfter(now()->addMinute()) === true) {
            return $account->access_token;
        }
        try {
            $response = self::token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]);
        } catch (ConnectionException) {
            throw new TransportException(__('Outlook didn\'t answer. Try again in a moment.'));
        }
        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token)) {
            // The permission was taken back, or the sign-in ran out: it has to be given again.
            if ($response->json('error') === 'invalid_grant') {
                $account->update(['verified_at' => null]);
            }
            throw new TransportException(__('Outlook no longer lets CareerCraft send for you. Connect Outlook again in the settings.'));
        }
        $refresh = $response->json('refresh_token');
        $expires = $response->json('expires_in');
        $account->update([
            'access_token' => $token,
            'refresh_token' => is_string($refresh) ? $refresh : $account->refresh_token,
            'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600),
        ]);

        return $token;
    }
}
