<?php

declare(strict_types=1);

use App\Livewire\Applications\Email;
use App\Livewire\Settings\MailAccount as MailAccountSettings;
use App\Mail\GmailTransport;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;

beforeEach(fn () => config(['services.google.client_id' => 'google-client', 'services.google.client_secret' => 'google-secret']));

/** What Google gives back after the person allows sending. */
function googleUser(array $scopes = ['openid', GmailTransport::SCOPE], ?string $refreshToken = 'refresh-1'): GoogleUser
{
    return (new GoogleUser)->setRaw([])->map(['id' => 'g-1', 'email' => 'lena.brandt@gmail.example', 'name' => 'Lena Brandt'])
        ->setToken('access-1')->setRefreshToken($refreshToken)->setExpiresIn(3600)->setApprovedScopes($scopes);
}

/** Google, as the controller drives it, answering with this user. */
function fakeGoogle(GoogleUser $user): void
{
    $google = Mockery::mock(GoogleProvider::class);
    $google->shouldReceive('redirectUrl')->andReturnSelf();
    $google->shouldReceive('user')->andReturn($user);
    Socialite::shouldReceive('driver')->with('google')->andReturn($google);
}

function gmailAccount(User $user, array $values = []): MailAccount
{
    return $user->mailAccount()->create(['provider' => 'gmail', 'access_token' => 'access-1', 'refresh_token' => 'refresh-1', 'token_expires_at' => now()->addHour(), 'from_address' => 'lena.brandt@gmail.example', 'from_name' => 'Lena Brandt', 'verified_at' => now(), ...$values]);
}

/** The raw email a request to the Gmail API carried. */
function gmailRaw(Request $request): string
{
    return (string) base64_decode(strtr((string) $request['raw'], '-_', '+/'));
}

describe('Connecting Gmail', function (): void {
    it('asks Google for permission to send email only, with a refresh token, coming back to the settings', function (): void {
        $location = $this->actingAs(User::factory()->create())->get(route('mail.gmail'))->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        expect($location)->toStartWith('https://accounts.google.com/o/oauth2/auth')
            ->and($query['scope'])->toContain(GmailTransport::SCOPE)
            ->and($query['scope'])->not->toContain('gmail.readonly')
            ->and($query)->toMatchArray(['access_type' => 'offline', 'prompt' => 'consent', 'redirect_uri' => route('mail.gmail.callback'), 'client_id' => 'google-client']);
    });

    it('is offered only when the runner has set Google\'s keys', function (): void {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertSee('Send through Gmail');

        config(['services.google.client_id' => null]);
        $this->actingAs($user)->get(route('mail.gmail'))->assertNotFound();
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertDontSee('Send through Gmail');
    });

    it('keeps the tokens encrypted, and sends a test email through Gmail', function (): void {
        Http::fake(['gmail.googleapis.com/*' => Http::response(['id' => 'm-1'])]);
        fakeGoogle(googleUser());
        $user = User::factory()->create(['name' => 'Lena Brandt']);

        $this->actingAs($user)->get(route('mail.gmail.callback'))->assertRedirect(route('settings'))
            ->assertSessionHas('status', 'Gmail is connected. A test email went to lena.brandt@gmail.example.');

        $account = $user->mailAccount()->sole();
        expect($account->only(['provider', 'from_address', 'access_token', 'refresh_token']))->toBe(['provider' => 'gmail', 'from_address' => 'lena.brandt@gmail.example', 'access_token' => 'access-1', 'refresh_token' => 'refresh-1'])
            ->and($account->verified_at)->not->toBeNull()
            ->and(DB::table('mail_accounts')->value('refresh_token'))->not->toContain('refresh-1');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && $request->hasHeader('Authorization', 'Bearer access-1')
            && str_contains(gmailRaw($request), 'Subject: CareerCraft test email')
            && str_contains(gmailRaw($request), 'To: lena.brandt@gmail.example'));
    });

    it('keeps a working mail server when the Gmail test fails', function (): void {
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Gmail API has not been used in project 1 before or it is disabled.']], 403)]);
        fakeGoogle(googleUser());
        $user = User::factory()->create();
        mailAccountFor($user);

        $this->actingAs($user)->get(route('mail.gmail.callback'))->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'The email could not be sent. Gmail refused the email: Gmail API has not been used in project 1 before or it is disabled.');

        expect($user->mailAccount()->sole()->only(['provider', 'host', 'password', 'refresh_token']))->toBe(['provider' => 'smtp', 'host' => 'smtp.mailbox.example', 'password' => 'app-password', 'refresh_token' => null])
            ->and($user->mailAccount()->sole()->verified_at)->not->toBeNull();
    });

    it('refuses a connection without permission to send, or without a refresh token', function (array $scopes, ?string $refreshToken): void {
        Http::fake();
        fakeGoogle(googleUser($scopes, $refreshToken));
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('mail.gmail.callback'))->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'Gmail was connected without permission to send email. Connect again and allow sending.');
        expect($user->mailAccount()->exists())->toBeFalse();
        Http::assertNothingSent();
    })->with([
        'no send permission' => [['openid', 'email'], 'refresh-1'],
        'no refresh token' => [['openid', GmailTransport::SCOPE], null],
    ]);
});

describe('Sending through Gmail', function (): void {
    it('sends the application with its attachments, refreshing a token that has run out', function (): void {
        Storage::fake('local');
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'access-2', 'expires_in' => 3599]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'm-2']),
        ]);
        $user = User::factory()->create(['name' => 'Lena Brandt']);
        gmailAccount($user, ['token_expires_at' => now()->subMinute()]);
        $application = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'Bewerbung als Product Designer', 'emailBody' => 'Anbei meine Bewerbung.'])
            ->call('send')->assertHasNoErrors()->assertSee('Sent to jobs@nordwind.example.');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'refresh-1' && $request['client_id'] === 'google-client');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'messages/send')
            && $request->hasHeader('Authorization', 'Bearer access-2')
            && str_contains(gmailRaw($request), 'filename=design-roles-version-2.pdf')
            && str_contains(gmailRaw($request), 'To: jobs@nordwind.example'));
        expect($user->mailAccount()->sole()->access_token)->toBe('access-2')
            ->and($application->emails()->count())->toBe(1);
    });

    it('asks to connect again when Google has taken the permission back', function (): void {
        Storage::fake('local');
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400), 'gmail.googleapis.com/*' => Http::response()]);
        $user = User::factory()->create();
        gmailAccount($user, ['token_expires_at' => now()->subMinute()]);
        $application = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'S', 'emailBody' => 'B'])->call('send')
            ->assertSee('Gmail no longer lets CareerCraft send for you.');

        expect($user->mailAccount()->sole()->verified_at)->toBeNull()
            ->and($application->emails()->count())->toBe(0);
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertSee(['Not connected', 'Connect Gmail again']);
    });

    it('says what Gmail refused', function (): void {
        Storage::fake('local');
        Http::fake(['gmail.googleapis.com/*' => Http::response(['error' => ['message' => 'Daily user sending limit exceeded.']], 429)]);
        $user = User::factory()->create();
        gmailAccount($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => recordedApplication($user)->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'S', 'emailBody' => 'B'])->call('send')
            ->assertSee('The email could not be sent. Gmail refused the email: Daily user sending limit exceeded.')
            ->assertDontSee('Your mail server said');
    });

    it('keeps a working Gmail connection when the mail server saved instead fails its test', function (): void {
        fakeMailServer(down: true);
        $user = User::factory()->create();
        gmailAccount($user);

        Livewire::actingAs($user)->test(MailAccountSettings::class)
            ->set(['mailHost' => 'smtp.mailbox.example', 'mailPassword' => 'wrong'])->call('save')
            ->assertHasErrors('test')->assertSet('mailPassword', 'wrong');

        expect($user->mailAccount()->sole()->only(['provider', 'refresh_token']))->toBe(['provider' => 'gmail', 'refresh_token' => 'refresh-1'])
            ->and($user->mailAccount()->sole()->verified_at)->not->toBeNull();
    });

    it('gives way to a mail server saved instead, dropping the tokens', function (): void {
        fakeMailServer();
        $user = User::factory()->create();
        gmailAccount($user);

        Livewire::actingAs($user)->test(MailAccountSettings::class)
            ->set(['mailHost' => 'smtp.mailbox.example', 'mailPassword' => 'app-password'])->call('save')->assertHasNoErrors();

        expect($user->mailAccount()->sole()->only(['provider', 'access_token', 'refresh_token', 'host']))->toBe(['provider' => 'smtp', 'access_token' => null, 'refresh_token' => null, 'host' => 'smtp.mailbox.example']);
    });
});
