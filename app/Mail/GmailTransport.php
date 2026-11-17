<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\MailAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Sends through the Gmail API with the candidate's permission (the gmail.send scope only),
 * refreshing the access token when it has run out. Failures are mail transport errors, so
 * they are told to the candidate like a mail server's.
 */
final class GmailTransport extends AbstractTransport
{
    public const string SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    public function __construct(private MailAccount $account)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail';
    }

    protected function doSend(SentMessage $message): void
    {
        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
        try {
            $response = Http::withToken($this->accessToken())->timeout(20)->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw]);
        } catch (ConnectionException) {
            throw new TransportException(__('Gmail didn\'t answer. Try again in a moment.'));
        }
        if ($response->failed()) {
            $reason = $response->json('error.message');
            throw new TransportException(__('Gmail refused the email: :reason', ['reason' => is_string($reason) ? $reason : (string) $response->status()]));
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
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $account->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new TransportException(__('Gmail didn\'t answer. Try again in a moment.'));
        }
        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token)) {
            // The permission was taken back, or the sign-in ran out: it has to be given again.
            if ($response->json('error') === 'invalid_grant') {
                $account->update(['verified_at' => null]);
            }
            throw new TransportException(__('Gmail no longer lets CareerCraft send for you. Connect Gmail again in the settings.'));
        }
        $expires = $response->json('expires_in');
        $account->update(['access_token' => $token, 'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600)]);

        return $token;
    }
}
