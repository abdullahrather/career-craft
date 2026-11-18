<?php

declare(strict_types=1);

namespace App\Inbox;

use App\Mail\OutlookTransport;
use App\Models\Mailbox;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads the inbox through Microsoft Graph with the candidate's read-only permission (Mail.Read:
 * nothing can be sent, changed or deleted). Graph has no IMAP UIDs, so an email's place is the
 * time it was received, in milliseconds, which only grows.
 */
final class OutlookReader implements MailReader
{
    /** Reading email, staying signed in for it, and reading the person's address. */
    public const string SCOPES = 'offline_access https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/User.Read';

    private const string API = 'https://graph.microsoft.com/v1.0/me';

    /** Whether Microsoft's granted scopes include reading email. */
    public static function canRead(string $scopes): bool
    {
        foreach (preg_split('/\s+/', $scopes) ?: [] as $scope) {
            if (strcasecmp(Str::afterLast($scope, '/'), 'Mail.Read') === 0) {
                return true;
            }
        }

        return false;
    }

    public function test(Mailbox $mailbox): ?string
    {
        try {
            $this->get($mailbox, self::API.'/mailFolders/inbox', ['$select' => 'id']);
        } catch (MailboxUnavailable $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    public function read(Mailbox $mailbox, ?int $afterUid, CarbonInterface $since, int $limit): array
    {
        $filter = $afterUid !== null
            ? 'receivedDateTime gt '.Carbon::createFromTimestampMs($afterUid)->utc()->format('Y-m-d\TH:i:s\Z')
            : 'receivedDateTime ge '.Carbon::instance($since)->utc()->format('Y-m-d\TH:i:s\Z');
        $url = self::API.'/mailFolders/inbox/messages';
        $query = [
            '$filter' => $filter,
            '$orderby' => 'receivedDateTime asc',
            '$select' => 'id,receivedDateTime,from,subject,internetMessageId,internetMessageHeaders',
            '$top' => 100,
        ];
        $messages = [];
        do {
            $page = $this->get($mailbox, $url, $query);
            foreach ((array) $page->json('value', []) as $item) {
                if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                    continue;
                }
                $message = $this->message($mailbox, $item['id'], $item);
                // Graph gives the time to the second, so emails received together share a place:
                // a batch never ends between them, or the next check would skip the rest.
                if (count($messages) >= $limit && $messages[count($messages) - 1]->uid !== $message->uid) {
                    return $messages;
                }
                $messages[] = $message;
            }
            // Read as one key: json() would take the dot for a nested one.
            $url = $page->collect()->get('@odata.nextLink');
            $query = [];
            // The token goes only to Graph, whatever the link says.
        } while (is_string($url) && str_starts_with($url, 'https://graph.microsoft.com/'));

        return $messages;
    }

    /**
     * An email by its headers; its text is fetched only when asked for.
     *
     * @param  array<mixed>  $item
     */
    private function message(Mailbox $mailbox, string $id, array $item): InboxMessage
    {
        $headers = [];
        foreach ((array) ($item['internetMessageHeaders'] ?? []) as $header) {
            if (is_array($header) && is_string($header['name'] ?? null) && is_string($header['value'] ?? null)) {
                $headers[Str::lower($header['name'])] = $header['value'];
            }
        }
        $address = data_get($item, 'from.emailAddress.address');
        $name = data_get($item, 'from.emailAddress.name');
        $received = is_string($item['receivedDateTime'] ?? null) ? Carbon::parse($item['receivedDateTime']) : null;
        $uid = $received?->getTimestampMs() ?? 0;
        $ids = fn (string $header): array => array_values(array_filter(array_map(fn (string $value): string => trim($value, '<> '), preg_split('/\s+/', $headers[$header] ?? '') ?: [])));
        $id = rawurlencode($id);

        return new InboxMessage(
            uid: $uid,
            messageId: trim(is_string($item['internetMessageId'] ?? null) ? $item['internetMessageId'] : '', '<> '),
            fromAddress: Str::lower(is_string($address) ? $address : ''),
            fromName: is_string($name) ? $name : '',
            subject: is_string($item['subject'] ?? null) ? $item['subject'] : '',
            date: Carbon::createFromTimestampMs($uid),
            references: [...$ids('in-reply-to'), ...$ids('references')],
            body: fn (): string => $this->text($this->get($mailbox, self::API."/messages/{$id}", ['$select' => 'body'], text: true)->json('body.content')),
        );
    }

    /** The email's text, as Graph gives it when asked for plain text. */
    private function text(mixed $content): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", is_string($content) ? $content : ''));
    }

    /**
     * A GET to Microsoft Graph, refreshing the access token when it has run out.
     *
     * @param  array<string, mixed>  $query
     */
    private function get(Mailbox $mailbox, string $url, array $query = [], bool $text = false): Response
    {
        // Graph's options start with $, kept as it is; spaces as %20.
        $query = str_replace('%24', '$', http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        try {
            $request = Http::withToken($this->accessToken($mailbox))->timeout(20);
            if ($text) {
                $request = $request->withHeaders(['Prefer' => 'outlook.body-content-type="text"']);
            }
            $response = $request->get($query !== '' ? $url.'?'.$query : $url);
        } catch (ConnectionException) {
            throw new MailboxUnavailable(__('Outlook didn\'t answer. Try again in a moment.'));
        }
        if ($response->failed()) {
            $reason = $response->json('error.message');
            $reason = is_string($reason) ? $reason : (string) $response->status();
            // The sign-in or its permission is gone: connecting again is the only way, so checks
            // wait for it. Other refusals, such as too many requests (429), pass by themselves.
            throw new MailboxUnavailable(__('Outlook refused to show the inbox: :reason', ['reason' => Str::limit($reason, 200)]), loginFailed: in_array($response->status(), [401, 403], true));
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
            $response = OutlookTransport::token(['grant_type' => 'refresh_token', 'refresh_token' => $mailbox->refresh_token, 'scope' => self::SCOPES]);
        } catch (ConnectionException) {
            throw new MailboxUnavailable(__('Outlook didn\'t answer. Try again in a moment.'));
        }
        $token = $response->json('access_token');
        if ($response->failed() || ! is_string($token)) {
            // The permission was taken back, or the sign-in ran out: it has to be given again.
            throw new MailboxUnavailable(__('Outlook no longer lets CareerCraft read your inbox. Connect Outlook again in the settings.'), loginFailed: $response->json('error') === 'invalid_grant');
        }
        // Microsoft may hand out a new refresh token each time; the newest is kept.
        $refresh = $response->json('refresh_token');
        $expires = $response->json('expires_in');
        $mailbox->fill([
            'access_token' => $token,
            'refresh_token' => is_string($refresh) ? $refresh : $mailbox->refresh_token,
            'token_expires_at' => now()->addSeconds(is_numeric($expires) ? (int) $expires : 3600),
        ]);
        // An unsaved mailbox, being tested, keeps the new token in memory only.
        if ($mailbox->exists) {
            $mailbox->save();
        }

        return $token;
    }
}
