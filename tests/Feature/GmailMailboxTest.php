<?php

declare(strict_types=1);

use App\Inbox\GmailReader;
use App\Inbox\MailboxCheck;
use App\Inbox\MailboxUnavailable;
use App\Livewire\Settings\Mailbox as MailboxSettings;
use App\Models\Application;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(fn () => config(['services.google.client_id' => 'google-client', 'services.google.client_secret' => 'google-secret']));

function gmailMailbox(User $user, array $values = []): Mailbox
{
    return $user->mailbox()->create(['provider' => 'gmail', 'username' => 'lena.brandt@gmail.example', 'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'token_expires_at' => now()->addHour(), ...$values]);
}

/** An email as the Gmail API describes it with format=metadata. */
function gmailMetadata(string $id, int $receivedMs, string $from, string $subject, array $headers = []): array
{
    $all = ['From' => $from, 'Subject' => $subject, 'Message-ID' => "<{$id}@mail.example>", ...$headers];

    return ['id' => $id, 'internalDate' => (string) $receivedMs, 'payload' => ['headers' => array_map(fn (string $name, string $value): array => ['name' => $name, 'value' => $value], array_keys($all), $all)]];
}

function base64url(string $text): string
{
    return rtrim(strtr(base64_encode($text), '+/', '-_'), '=');
}

describe('Connecting Gmail for replies', function (): void {
    it('asks Google for permission to read email only, with a refresh token', function (): void {
        $location = $this->actingAs(User::factory()->create())->get(route('mailbox.gmail'))->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        expect($query['scope'])->toContain(GmailReader::SCOPE)
            ->and($query['scope'])->not->toContain('gmail.send')
            ->and($query['scope'])->not->toContain('mail.google.com')
            ->and($query)->toMatchArray(['access_type' => 'offline', 'prompt' => 'consent', 'redirect_uri' => route('mailbox.gmail.callback')]);
    });

    it('is offered only when the runner has set Google\'s keys', function (): void {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(MailboxSettings::class)->assertSee(['Read replies in Gmail', 'CareerCraft can\'t send, change or delete anything']);

        config(['services.google.client_id' => null]);
        $this->actingAs($user)->get(route('mailbox.gmail'))->assertNotFound();
        $this->actingAs($user)->get(route('mailbox.gmail.callback'))->assertNotFound();
        Livewire::actingAs($user)->test(MailboxSettings::class)->assertDontSee('Read replies in Gmail');
    });

    it('tests the inbox, then saves it with the tokens encrypted in place of an IMAP mailbox', function (): void {
        Http::fake(['gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'lena.brandt@gmail.example'])]);
        fakeGoogle(googleUser(['openid', GmailReader::SCOPE]));
        $user = User::factory()->create();
        mailboxFor($user, ['last_uid' => 40]);

        $this->actingAs($user)->get(route('mailbox.gmail.callback'))->assertRedirect(route('settings'))
            ->assertSessionHas('status', 'Gmail is connected for replies: lena.brandt@gmail.example. CareerCraft checks it every 15 minutes.');

        $mailbox = $user->mailbox()->sole();
        expect($mailbox->only(['provider', 'username', 'host', 'password', 'access_token', 'refresh_token', 'last_uid']))->toBe(['provider' => 'gmail', 'username' => 'lena.brandt@gmail.example', 'host' => null, 'password' => null, 'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'last_uid' => null])
            ->and(DB::table('mailboxes')->value('refresh_token'))->not->toContain('refresh-1')
            ->and($mailbox->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer access-1'));

        Livewire::actingAs($user)->test(MailboxSettings::class)->assertSee(['Gmail:', 'lena.brandt@gmail.example, not checked yet.', 'Connect Gmail again']);
    });

    it('refuses a connection without permission to read, or without a refresh token', function (array $scopes, ?string $refreshToken): void {
        Http::fake();
        fakeGoogle(googleUser($scopes, $refreshToken));
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('mailbox.gmail.callback'))->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'Gmail was connected without permission to read email. Connect again and allow reading.');
        expect($user->mailbox()->exists())->toBeFalse();
        Http::assertNothingSent();
    })->with([
        'only send permission' => [['openid', 'https://www.googleapis.com/auth/gmail.send'], 'refresh-1'],
        'no refresh token' => [['openid', GmailReader::SCOPE], null],
    ]);

    it('keeps the mailbox there was when Gmail can\'t be read', function (): void {
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Gmail API has not been used in project 1 before or it is disabled.']], 403)]);
        fakeGoogle(googleUser(['openid', GmailReader::SCOPE]));
        $user = User::factory()->create();
        mailboxFor($user);

        $this->actingAs($user)->get(route('mailbox.gmail.callback'))
            ->assertSessionHas('error', 'Gmail refused to show the inbox: Gmail API has not been used in project 1 before or it is disabled.');
        expect($user->mailbox()->sole()->only(['provider', 'host', 'password']))->toBe(['provider' => 'imap', 'host' => 'imap.mailbox.example', 'password' => 'app-password']);
    });

    it('gives way to an IMAP mailbox saved instead, dropping the tokens', function (): void {
        fakeInbox();
        $user = User::factory()->create();
        gmailMailbox($user, ['last_uid' => 1_790_000_000_000]);

        Livewire::actingAs($user)->test(MailboxSettings::class)
            ->set(['boxHost' => 'imap.mailbox.example', 'boxUsername' => 'lena@mailbox.example'])->call('save')->assertHasErrors('boxPassword')
            ->set('boxPassword', 'app-password')->call('save')->assertHasNoErrors();
        expect($user->mailbox()->sole()->only(['provider', 'host', 'access_token', 'refresh_token', 'last_uid']))->toBe(['provider' => 'imap', 'host' => 'imap.mailbox.example', 'access_token' => null, 'refresh_token' => null, 'last_uid' => null]);
    });
});

describe('Reading Gmail', function (): void {
    it('lists new inbox emails, oldest first, by their headers, and reads the text of the matching one only', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        $mailbox = gmailMailbox($user);
        $since = now()->subDays(30)->getTimestamp();
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'b'], ['id' => 'a']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/a?format=metadata*' => Http::response(gmailMetadata('a', 1_795_000_000_000, 'Shop <news@shop.example>', 'Offers')),
            'gmail.googleapis.com/gmail/v1/users/me/messages/b?format=metadata*' => Http::response(gmailMetadata('b', 1_795_000_100_000, '"Nordwind Logistik Recruiting" <Karriere@nordwind-logistik.de>', 'Einladung: Product Designer', ['In-Reply-To' => '<sent-1@mailbox.example>'])),
            'gmail.googleapis.com/gmail/v1/users/me/messages/b?format=full' => Http::response(['payload' => ['mimeType' => 'multipart/alternative', 'parts' => [
                ['mimeType' => 'text/html', 'body' => ['data' => base64url('<p>Hallo</p>')]],
                ['mimeType' => 'text/plain', 'body' => ['data' => base64url("Hallo Lena,\r\n\r\nwir laden Sie ein.")]],
            ]]]),
        ]);

        expect(app(MailboxCheck::class)->run($mailbox))->toBe(1);

        $reply = $application->receivedEmails()->sole();
        expect($reply->only(['message_id', 'from_address', 'from_name', 'subject', 'body']))->toBe(['message_id' => 'b@mail.example', 'from_address' => 'karriere@nordwind-logistik.de', 'from_name' => 'Nordwind Logistik Recruiting', 'subject' => 'Einladung: Product Designer', 'body' => "Hallo Lena,\n\nwir laden Sie ein."])
            ->and($reply->received_at->getTimestampMs())->toBe(1_795_000_100_000)
            ->and($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => 1_795_000_100_000, 'error' => null]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/messages?')
            && $request['q'] === "in:inbox after:{$since}" && $request->hasHeader('Authorization', 'Bearer access-1'));
        // Google wants a list as a repeated key.
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages/b?format=metadata&metadataHeaders=From&metadataHeaders=Subject&metadataHeaders=Message-ID'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages/a?format=full'));
    });

    it('picks up after the last email it saw, and keeps a batch to the oldest', function (): void {
        $mailbox = gmailMailbox(User::factory()->create());
        Http::fake([
            'gmail.googleapis.com/gmail/v1/users/me/messages?*' => Http::response(['messages' => [['id' => 'c'], ['id' => 'b'], ['id' => 'a']]]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/a?*' => Http::response(gmailMetadata('a', 1_795_000_000_500, 'x@shop.example', 'Seen already')),
            'gmail.googleapis.com/gmail/v1/users/me/messages/b?*' => Http::response(gmailMetadata('b', 1_795_000_001_000, 'x@shop.example', 'New')),
            'gmail.googleapis.com/gmail/v1/users/me/messages/c?*' => Http::response(gmailMetadata('c', 1_795_000_002_000, 'x@shop.example', 'Newer')),
        ]);

        $emails = (new GmailReader)->read($mailbox, 1_795_000_000_500, now(), 1);
        expect(array_map(fn ($email) => $email->subject, $emails))->toBe(['New']);
        Http::assertSent(fn (Request $request): bool => ($request['q'] ?? null) === 'in:inbox after:1795000000');
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages/c?'));
    });

    it('refreshes a token that has run out, and keeps the new one', function (): void {
        $mailbox = gmailMailbox(User::factory()->create(), ['token_expires_at' => now()->subMinute()]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-2', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['messages' => []]),
        ]);

        expect((new GmailReader)->read($mailbox, null, now()->subDay(), 200))->toBe([])
            ->and($mailbox->refresh()->access_token)->toBe('access-2');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token' && $request['refresh_token'] === 'refresh-1' && $request['client_id'] === 'google-client');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'gmail.googleapis.com') && $request->hasHeader('Authorization', 'Bearer access-2'));
    });

    it('stops checking when Google has taken the permission back, and only then', function (int $status, array $answer, bool $loginFailed): void {
        $user = User::factory()->create();
        $mailbox = gmailMailbox($user, ['token_expires_at' => now()->subMinute()]);
        Http::fake(['oauth2.googleapis.com/token' => Http::response($answer, $status)]);

        app(MailboxCheck::class)->run($mailbox);
        expect($mailbox->refresh()->only(['error', 'login_failed']))->toBe(['error' => 'Gmail no longer lets CareerCraft read your inbox. Connect Gmail again in the settings.', 'login_failed' => $loginFailed]);
    })->with([
        'permission taken back' => [400, ['error' => 'invalid_grant'], true],
        'Google having a bad moment' => [500, ['error' => 'internal_failure'], false],
    ]);

    it('stops checking when Gmail no longer allows reading, but not when it doesn\'t answer', function (): void {
        $mailbox = gmailMailbox(User::factory()->create());
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Request had insufficient authentication scopes.']], 403)]);
        expect(fn () => (new GmailReader)->read($mailbox, null, now(), 200))->toThrow(fn (MailboxUnavailable $exception) => expect($exception->loginFailed)->toBeTrue());

        Http::fake(['gmail.googleapis.com/*' => fn () => throw new ConnectionException('timed out')]);
        app(MailboxCheck::class)->run($mailbox);
        expect($mailbox->refresh()->only(['error', 'login_failed']))->toBe(['error' => 'Gmail didn\'t answer. Try again in a moment.', 'login_failed' => false]);
    });
});
