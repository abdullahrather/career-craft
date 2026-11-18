<?php

declare(strict_types=1);

use App\Inbox\MailboxCheck;
use App\Inbox\MailboxUnavailable;
use App\Inbox\OutlookReader;
use App\Livewire\Settings\Mailbox as MailboxSettings;
use App\Models\Application;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(fn () => config(['services.microsoft.client_id' => 'ms-client', 'services.microsoft.client_secret' => 'ms-secret', 'services.microsoft.tenant' => 'common']));

function outlookMailbox(User $user, array $values = []): Mailbox
{
    return $user->mailbox()->create(['provider' => 'outlook', 'username' => 'lena.brandt@outlook.example', 'access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'token_expires_at' => now()->addHour(), ...$values]);
}

/** Signs in with Microsoft for reading as far as the way back, with the state the redirect stored. */
function outlookMailboxCallback(User $user, ?string $state = null): TestResponse
{
    test()->actingAs($user)->get(route('mailbox.outlook'));

    return test()->actingAs($user)->get(route('mailbox.outlook.callback', ['code' => 'code-1', 'state' => $state ?? session('outlook_mailbox_state')]));
}

/** Microsoft's answers to a sign-in that allowed reading, and Graph opening the inbox. */
function fakeMicrosoftReading(array $tokens = [], array $inbox = ['id' => 'inbox-1'], int $status = 200): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([...['access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'expires_in' => 3600, 'scope' => 'https://graph.microsoft.com/Mail.Read https://graph.microsoft.com/User.Read'], ...$tokens]),
        'graph.microsoft.com/v1.0/me?*' => Http::response(['mail' => 'Lena.Brandt@outlook.example', 'userPrincipalName' => 'lena.brandt@outlook.example']),
        'graph.microsoft.com/v1.0/me/mailFolders/inbox?*' => Http::response($inbox, $status),
    ]);
}

/** An email as Graph lists it with the $select the reader asks for. */
function graphEmail(string $id, string $received, string $from, string $name, string $subject, array $headers = []): array
{
    $all = ['Message-ID' => "<{$id}@mail.example>", ...$headers];

    return [
        'id' => $id,
        'receivedDateTime' => $received,
        'from' => ['emailAddress' => ['address' => $from, 'name' => $name]],
        'subject' => $subject,
        'internetMessageId' => "<{$id}@mail.example>",
        'internetMessageHeaders' => array_map(fn (string $header, string $value): array => ['name' => $header, 'value' => $value], array_keys($all), $all),
    ];
}

describe('Connecting Outlook for replies', function (): void {
    it('asks Microsoft for permission to read email only, staying signed in, with a state to check', function (): void {
        $location = $this->actingAs(User::factory()->create())->get(route('mailbox.outlook'))->assertRedirect()->headers->get('Location');

        expect((string) $location)->toStartWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        expect($query)->toMatchArray(['client_id' => 'ms-client', 'response_type' => 'code', 'redirect_uri' => route('mailbox.outlook.callback'), 'state' => session('outlook_mailbox_state'), 'scope' => OutlookReader::SCOPES])
            ->and($query['scope'])->toContain('offline_access')
            ->and($query['scope'])->not->toContain('Mail.Send')
            ->and($query['scope'])->not->toContain('ReadWrite')
            ->and(strlen((string) session('outlook_mailbox_state')))->toBe(40);
    });

    it('is offered only when the runner has set Microsoft\'s keys', function (): void {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(MailboxSettings::class)->assertSee(['Read replies in Outlook', 'CareerCraft can\'t send, change or delete anything in your mailbox']);

        config(['services.microsoft.client_id' => null]);
        $this->actingAs($user)->get(route('mailbox.outlook'))->assertNotFound();
        $this->actingAs($user)->get(route('mailbox.outlook.callback'))->assertNotFound();
        Livewire::actingAs($user)->test(MailboxSettings::class)->assertDontSee('Read replies in Outlook');
    });

    it('turns away a way back with another state, without asking Microsoft for tokens', function (): void {
        Http::fake();
        $user = User::factory()->create();

        outlookMailboxCallback($user, 'someone-elses-state')->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'Connecting Outlook did not work. Please try again.');
        expect($user->mailbox()->exists())->toBeFalse();
        Http::assertNothingSent();
    });

    it('tests the inbox, then saves it with the tokens encrypted in place of an IMAP mailbox', function (): void {
        fakeMicrosoftReading();
        $user = User::factory()->create();
        mailboxFor($user, ['last_uid' => 40]);

        outlookMailboxCallback($user)->assertRedirect(route('settings'))
            ->assertSessionHas('status', 'Outlook is connected for replies: lena.brandt@outlook.example. CareerCraft checks it every 15 minutes.');

        $mailbox = $user->mailbox()->sole();
        expect($mailbox->only(['provider', 'username', 'host', 'password', 'access_token', 'refresh_token', 'last_uid']))->toBe(['provider' => 'outlook', 'username' => 'lena.brandt@outlook.example', 'host' => null, 'password' => null, 'access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'last_uid' => null])
            ->and(DB::table('mailboxes')->value('refresh_token'))->not->toContain('ms-refresh-1')
            ->and($mailbox->toArray())->not->toHaveKeys(['access_token', 'refresh_token']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/common/oauth2/v2.0/token'
            && $request['grant_type'] === 'authorization_code' && $request['code'] === 'code-1' && $request['scope'] === OutlookReader::SCOPES && $request['redirect_uri'] === route('mailbox.outlook.callback'));
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox?') && $request->hasHeader('Authorization', 'Bearer ms-access-1'));

        Livewire::actingAs($user)->test(MailboxSettings::class)->assertSee(['Outlook:', 'lena.brandt@outlook.example, not checked yet.', 'Connect Outlook again']);
    });

    it('carries on where it was when the same account connects again', function (): void {
        fakeMicrosoftReading();
        $user = User::factory()->create();
        outlookMailbox($user, ['last_uid' => 1_795_000_000_000, 'login_failed' => true, 'error' => 'Outlook said no.']);

        outlookMailboxCallback($user)->assertSessionHas('status');
        expect($user->mailbox()->sole()->only(['last_uid', 'login_failed', 'error']))->toBe(['last_uid' => 1_795_000_000_000, 'login_failed' => false, 'error' => null]);
    });

    it('refuses a connection without permission to read, or without a refresh token', function (array $tokens): void {
        fakeMicrosoftReading($tokens);
        $user = User::factory()->create();

        outlookMailboxCallback($user)->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'Outlook was connected without permission to read email. Connect again and allow reading.');
        expect($user->mailbox()->exists())->toBeFalse();
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'mailFolders'));
    })->with([
        'only send permission' => [['scope' => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read']],
        'no refresh token' => [['refresh_token' => null]],
    ]);

    it('keeps the mailbox there was when Outlook can\'t be read', function (): void {
        fakeMicrosoftReading(inbox: ['error' => ['code' => 'MailboxNotEnabledForRESTAPI', 'message' => 'The mailbox is either inactive, soft-deleted, or is hosted on-premise.']], status: 404);
        $user = User::factory()->create();
        mailboxFor($user);

        outlookMailboxCallback($user)
            ->assertSessionHas('error', 'Outlook refused to show the inbox: The mailbox is either inactive, soft-deleted, or is hosted on-premise.');
        expect($user->mailbox()->sole()->only(['provider', 'host', 'password']))->toBe(['provider' => 'imap', 'host' => 'imap.mailbox.example', 'password' => 'app-password']);
    });
});

describe('Reading Outlook', function (): void {
    it('lists new inbox emails, oldest first, by their headers, and reads the text of the matching one only', function (): void {
        $user = User::factory()->create();
        $application = Application::track($user, ['title' => 'Product Designer', 'company' => 'Nordwind Logistik']);
        $mailbox = outlookMailbox($user);
        $since = now()->subDays(30)->utc()->format('Y-m-d\TH:i:s\Z');
        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?*' => Http::response(['value' => [
                graphEmail('a', '2026-11-18T09:00:00Z', 'news@shop.example', 'Shop', 'Offers'),
                graphEmail('b', '2026-11-18T09:05:00Z', 'Karriere@nordwind-logistik.de', 'Nordwind Logistik Recruiting', 'Einladung: Product Designer', ['In-Reply-To' => '<sent-1@mailbox.example>']),
            ]]),
            'graph.microsoft.com/v1.0/me/messages/b?*' => Http::response(['body' => ['contentType' => 'text', 'content' => "Hallo Lena,\r\n\r\nwir laden Sie ein.\r\n"]]),
        ]);

        expect(app(MailboxCheck::class)->run($mailbox))->toBe(1);

        $reply = $application->receivedEmails()->sole();
        $received = Carbon::parse('2026-11-18T09:05:00Z')->getTimestampMs();
        expect($reply->only(['message_id', 'from_address', 'from_name', 'subject', 'body']))->toBe(['message_id' => 'b@mail.example', 'from_address' => 'karriere@nordwind-logistik.de', 'from_name' => 'Nordwind Logistik Recruiting', 'subject' => 'Einladung: Product Designer', 'body' => "Hallo Lena,\n\nwir laden Sie ein."])
            ->and($reply->received_at->getTimestampMs())->toBe($received)
            ->and($mailbox->refresh()->only(['last_uid', 'error']))->toBe(['last_uid' => $received, 'error' => null]);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$filter=')
            && $request['$filter'] === "receivedDateTime ge {$since}" && $request['$orderby'] === 'receivedDateTime asc'
            && str_contains($request['$select'], 'internetMessageHeaders') && $request->hasHeader('Authorization', 'Bearer ms-access-1'));
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.microsoft.com/v1.0/me/messages/b?') && $request->hasHeader('Prefer', 'outlook.body-content-type="text"'));
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'messages/a?'));
    });

    it('picks up after the last email it saw, and ends a batch only between seconds', function (): void {
        $mailbox = outlookMailbox(User::factory()->create());
        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?*' => Http::response(['value' => [
                graphEmail('b', '2026-11-18T09:00:01Z', 'x@shop.example', 'Shop', 'New'),
                graphEmail('c', '2026-11-18T09:00:01Z', 'x@shop.example', 'Shop', 'Same second'),
                graphEmail('d', '2026-11-18T09:00:02Z', 'x@shop.example', 'Shop', 'Next check'),
            ]]),
        ]);

        $emails = (new OutlookReader)->read($mailbox, Carbon::parse('2026-11-18T09:00:00Z')->getTimestampMs(), now(), 1);
        expect(array_map(fn ($email) => $email->subject, $emails))->toBe(['New', 'Same second']);
        Http::assertSent(fn (Request $request): bool => ($request['$filter'] ?? null) === 'receivedDateTime gt 2026-11-18T09:00:00Z');
    });

    it('follows the next page from Graph, and only to Graph', function (): void {
        $mailbox = outlookMailbox(User::factory()->create());
        Http::fake([
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skip=1' => Http::response(['value' => [graphEmail('b', '2026-11-18T09:00:02Z', 'x@shop.example', 'Shop', 'Second page')], '@odata.nextLink' => 'https://elsewhere.example/messages?$skip=2']),
            'graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?*' => Http::response(['value' => [graphEmail('a', '2026-11-18T09:00:01Z', 'x@shop.example', 'Shop', 'First page')], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skip=1']),
            '*' => Http::response(['value' => []]),
        ]);

        $emails = (new OutlookReader)->read($mailbox, null, now()->subDay(), 200);
        expect(array_map(fn ($email) => $email->subject, $emails))->toBe(['First page', 'Second page']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'elsewhere.example'));
    });

    it('refreshes a token that has run out, keeping the refresh token Microsoft hands out', function (): void {
        $mailbox = outlookMailbox(User::factory()->create(), ['token_expires_at' => now()->subMinute()]);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'ms-access-2', 'refresh_token' => 'ms-refresh-2', 'expires_in' => 3599]),
            'graph.microsoft.com/*' => Http::response(['value' => []]),
        ]);

        expect((new OutlookReader)->read($mailbox, null, now()->subDay(), 200))->toBe([])
            ->and($mailbox->refresh()->only(['access_token', 'refresh_token']))->toBe(['access_token' => 'ms-access-2', 'refresh_token' => 'ms-refresh-2']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'login.microsoftonline.com') && $request['refresh_token'] === 'ms-refresh-1' && $request['scope'] === OutlookReader::SCOPES && $request['client_id'] === 'ms-client');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'graph.microsoft.com') && $request->hasHeader('Authorization', 'Bearer ms-access-2'));
    });

    it('stops checking when Microsoft has taken the permission back, and only then', function (int $status, array $answer, bool $loginFailed): void {
        $mailbox = outlookMailbox(User::factory()->create(), ['token_expires_at' => now()->subMinute()]);
        Http::fake(['login.microsoftonline.com/*' => Http::response($answer, $status)]);

        app(MailboxCheck::class)->run($mailbox);
        expect($mailbox->refresh()->only(['error', 'login_failed']))->toBe(['error' => 'Outlook no longer lets CareerCraft read your inbox. Connect Outlook again in the settings.', 'login_failed' => $loginFailed]);
    })->with([
        'permission taken back' => [400, ['error' => 'invalid_grant'], true],
        'Microsoft having a bad moment' => [503, ['error' => 'temporarily_unavailable'], false],
    ]);

    it('stops checking when Graph no longer allows reading, and only then', function (int $status, string $code, bool $loginFailed): void {
        $mailbox = outlookMailbox(User::factory()->create());
        Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => $code, 'message' => 'Graph said no.']], $status)]);

        expect(fn () => (new OutlookReader)->read($mailbox, null, now(), 200))->toThrow(fn (MailboxUnavailable $exception) => expect($exception->loginFailed)->toBe($loginFailed));
    })->with([
        'sign-in no longer valid' => [401, 'InvalidAuthenticationToken', true],
        'permission missing' => [403, 'ErrorAccessDenied', true],
        'too many requests for now' => [429, 'ApplicationThrottled', false],
        'Graph having a bad moment' => [503, 'ServiceNotAvailable', false],
    ]);

    it('keeps checking when Outlook doesn\'t answer', function (): void {
        $mailbox = outlookMailbox(User::factory()->create());

        Http::fake(['graph.microsoft.com/*' => fn () => throw new ConnectionException('timed out')]);
        app(MailboxCheck::class)->run($mailbox);
        expect($mailbox->refresh()->only(['error', 'login_failed']))->toBe(['error' => 'Outlook didn\'t answer. Try again in a moment.', 'login_failed' => false]);
    });
});
