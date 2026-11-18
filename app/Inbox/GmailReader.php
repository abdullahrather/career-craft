<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Models\Mailbox;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Reads the inbox through the Gmail API with the candidate's read-only permission (the
 * gmail.readonly scope: nothing can be sent, changed or deleted). Gmail has no IMAP UIDs, so
 * an email's place is the time Gmail received it, in milliseconds, which only grows.
 */
final class GmailReader implements MailReader
{
    public const string SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    private const string API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    /** At most this many emails are listed in one check, so a full inbox can't run on and on. */
    private const int LIST_LIMIT = 5000;

    public function test(Mailbox $mailbox): ?string
    {
        try {
            $this->get($mailbox, '/profile');
        } catch (MailboxUnavailable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
    {
        // Gmail's search goes by the second; the milliseconds are compared below.
        $after = $afterUid !== null ? intdiv($afterUid, 1000) : $since->getTimestamp();
        $ids = [];
        $page = null;
        do {
            $list = $this->get($mailbox, '/messages', array_filter(['q' => "in:inbox after:{$after}", 'maxResults' => 500, 'pageToken' => $page]));
            foreach ((array) $list->json('messages', []) as $message) {
                if (is_array($message) && is_string($message['id'] ?? null)) {
                    $ids[] = $message['id'];
                }
            }
            $page = $list->json('nextPageToken');
        } while (is_string($page) && count($ids) < self::LIST_LIMIT);

        // Gmail lists the newest first.
        $messages = [];
        foreach (array_reverse($ids) as $id) {
            if (count($messages) >= $limit) {
                break;
            }
            $message = $this->message($mailbox, $id);
            if ($afterUid === null || $message->uid > $afterUid) {
                $messages[] = $message;
            }
        }
        usort($messages, fn (InboxMessage $a, InboxMessage $b): int => $a->uid <=> $b->uid);

        return $messages;
    }

    /** An email by its headers; its text is fetched only when asked for. */
    private function message(Mailbox $mailbox, string $id): InboxMessage
    {
        $response = $this->get($mailbox, "/messages/{$id}", ['format' => 'metadata', 'metadataHeaders' => ['From', 'Subject', 'Message-ID', 'In-Reply-To', 'References']]);
        $headers = [];
        foreach ((array) $response->json('payload.headers', []) as $header) {
            if (is_array($header) && is_string($header['name'] ?? null) && is_string($header['value'] ?? null)) {
                $headers[Str::lower($header['name'])] = $header['value'];
            }
        }
        [$address, $name] = $this->sender($headers['from'] ?? '');
        $received = $response->json('internalDate');
        $uid = is_numeric($received) ? (int) $received : 0;
        $ids = fn (string $header): array => array_values(array_filter(array_map(fn (string $value): string => trim($value, '<> '), preg_split('/\s+/', $headers[$header] ?? '') ?: [])));

        return new InboxMessage(
            uid: $uid,
            messageId: trim($headers['message-id'] ?? '', '<> '),
            fromAddress: Str::lower($address),
            fromName: $name,
            subject: $headers['subject'] ?? '',
            date: Carbon::createFromTimestampMs($uid),
            references: [...$ids('in-reply-to'), ...$ids('references')],
            body: fn (): string => $this->text($this->get($mailbox, "/messages/{$id}", ['format' => 'full'])->json('payload')),
        );
    }

    /** @return array{0: string, 1: string} The sender's address and name. */
    private function sender(string $from): array
    {
        try {
            $address = Address::create($from);

            return [$address->getAddress(), $address->getName()];
        } catch (Throwable) {
            return [preg_match('/[^\s<>"]+@[^\s<>"]+/', $from, $match) === 1 ? $match[0] : '', ''];
        }
    }

    /** The email's plain text, or its HTML without tags when it has none. */
    private function text(mixed $payload): string
    {
        $parts = ['text/plain' => null, 'text/html' => null];
        $walk = function (mixed $part) use (&$walk, &$parts): void {
            if (! is_array($part)) {
                return;
            }
            $type = $part['mimeType'] ?? null;
            $body = $part['body'] ?? null;
            $data = is_array($body) ? ($body['data'] ?? null) : null;
            if (is_string($type) && array_key_exists($type, $parts) && $parts[$type] === null && is_string($data)) {
                $parts[$type] = (string) base64_decode(strtr($data, '-_', '+/'));
            }
            foreach ((array) ($part['parts'] ?? []) as $child) {
                $walk($child);
            }
        };
        $walk($payload);
        $text = $parts['text/plain'] ?? strip_tags((string) $parts['text/html']);

        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    /**
     * A GET to the Gmail API, refreshing the access token when it has run out.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(Mailbox $mailbox, string $path, array $query = []): Response
    {
        // A list repeats its key (metadataHeaders=From&metadataHeaders=Subject), as Google expects.
        $query = (string) preg_replace('/%5B\d+%5D=/', '=', http_build_query($query));
        try {
            $response = Http::withToken($this->accessToken($mailbox))->timeout(20)->get(self::API.$path, $query);
        } catch (ConnectionException) {
            throw new MailboxUnavailable(__('Gmail didn\'t answer. Try again in a moment.'));
        }
        if ($response->failed()) {
            $reason = $response->json('error.message');
            $reason = is_string($reason) ? $reason : (string) $response->status();
            // The sign-in or its permission is gone: connecting again is the only way, so checks
            // wait for it. Other refusals, such as a rate limit (also a 403), pass by themselves.
            $why = $response->json('error.errors.0.reason');
            $refused = $response->status() === 401 || ($response->status() === 403 && in_array($why, ['insufficientPermissions', 'forbidden'], true));
            throw new MailboxUnavailable(__('Gmail refused to show the inbox: :reason', ['reason' => Str::limit($reason, 200)]), loginFailed: $refused);
        }

        return $response;
    }

    /** A valid access token, refreshed with the refresh token when needed. */
    private function accessToken(Mailbox $mailbox): string
    {
        if ($mailbox->access_token !== null && $mailbox->token_expires_at?->isAfter(now()->addMinute()) === true) {
            return $mailbox->access_token;
        }
        try {
            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $mailbox->refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        } catch (ConnectionException) {
            throw new MailboxUnavailable(__('Gmail didn\'t answer. Try again in a moment.'));
        }
        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token)) {
            // The permission was taken back, or the sign-in ran out: it has to be given again.
            throw new MailboxUnavailable(__('Gmail no longer lets CareerCraft read your inbox. Connect Gmail again in the settings.'), loginFailed: $response->json('error') === 'invalid_grant');
        }
        $expires = $response->json('expires_in');
        // An unsaved mailbox, being tested, keeps the new token in memory only.
        $mailbox->fill(['access_token' => $token, 'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600)]);
        if ($mailbox->exists) {
            $mailbox->save();
        }

        return $token;
    }
}
