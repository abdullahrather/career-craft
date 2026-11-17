<?php

declare(strict_types=1);

use App\Livewire\Applications\Email;
use App\Livewire\Settings\MailAccount as MailAccountSettings;
use App\Models\MailAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

beforeEach(fn () => config(['services.microsoft.client_id' => 'ms-client', 'services.microsoft.client_secret' => 'ms-secret', 'services.microsoft.tenant' => 'common']));

/** Microsoft's answers to a sign-in that allowed sending, and Graph accepting emails. */
function fakeMicrosoft(array $tokens = [], array $send = []): void
{
    Http::fake([
        'login.microsoftonline.com/*' => Http::response([...['access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'expires_in' => 3600, 'scope' => 'https://graph.microsoft.com/Mail.Send https://graph.microsoft.com/User.Read'], ...$tokens]),
        'graph.microsoft.com/v1.0/me?*' => Http::response(['mail' => 'lena.brandt@outlook.example', 'userPrincipalName' => 'lena.brandt@outlook.example']),
        'graph.microsoft.com/v1.0/me/sendMail' => Http::response($send['body'] ?? '', $send['status'] ?? 202),
    ]);
}

function outlookAccount(User $user, array $values = []): MailAccount
{
    return $user->mailAccount()->create(['provider' => 'outlook', 'access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1', 'token_expires_at' => now()->addHour(), 'from_address' => 'lena.brandt@outlook.example', 'from_name' => 'Lena Brandt', 'verified_at' => now(), ...$values]);
}

/** Signs in with Microsoft as far as the way back, with the state the redirect stored. */
function outlookCallback(User $user, ?string $state = null): TestResponse
{
    test()->actingAs($user)->get(route('mail.outlook'));

    return test()->actingAs($user)->get(route('mail.outlook.callback', ['code' => 'code-1', 'state' => $state ?? session('outlook_state')]));
}

describe('Connecting Outlook', function (): void {
    it('asks Microsoft for permission to send email only, staying signed in, with a state to check', function (): void {
        $location = $this->actingAs(User::factory()->create())->get(route('mail.outlook'))->assertRedirect()->headers->get('Location');

        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        expect($location)->toStartWith('https://login.microsoftonline.com/common/oauth2/v2.0/authorize?')
            ->and($query)->toMatchArray(['client_id' => 'ms-client', 'response_type' => 'code', 'redirect_uri' => route('mail.outlook.callback'), 'state' => session('outlook_state')])
            ->and(explode(' ', $query['scope']))->toBe(['offline_access', 'https://graph.microsoft.com/Mail.Send', 'https://graph.microsoft.com/User.Read'])
            ->and(strlen((string) session('outlook_state')))->toBe(40);
    });

    it('is offered only when the runner has set Microsoft\'s keys', function (): void {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertSee('Send through Outlook');

        config(['services.microsoft.client_id' => null]);
        $this->actingAs($user)->get(route('mail.outlook'))->assertNotFound();
        $this->actingAs($user)->get(route('mail.outlook.callback'))->assertNotFound();
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertDontSee('Send through Outlook');
    });

    it('turns away a way back with another state, without asking Microsoft for tokens', function (): void {
        Http::fake();
        $user = User::factory()->create();

        outlookCallback($user, 'someone-elses-state')->assertRedirect(route('settings'))
            ->assertSessionHas('error', 'Connecting Outlook did not work. Please try again.');
        $this->actingAs($user)->get(route('mail.outlook.callback', ['code' => 'code-1']))->assertSessionHas('error');

        Http::assertNothingSent();
        expect($user->mailAccount()->exists())->toBeFalse();
    });

    it('keeps the tokens encrypted, and sends a test email through Outlook', function (): void {
        fakeMicrosoft();
        $user = User::factory()->create(['name' => 'Lena Brandt']);

        outlookCallback($user)->assertRedirect(route('settings'))
            ->assertSessionHas('status', 'Outlook is connected. A test email went to lena.brandt@outlook.example.');

        $account = $user->mailAccount()->sole();
        expect($account->only(['provider', 'from_address', 'access_token', 'refresh_token']))->toBe(['provider' => 'outlook', 'from_address' => 'lena.brandt@outlook.example', 'access_token' => 'ms-access-1', 'refresh_token' => 'ms-refresh-1'])
            ->and($account->verified_at)->not->toBeNull()
            ->and(DB::table('mail_accounts')->value('refresh_token'))->not->toContain('ms-refresh-1');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/oauth2/v2.0/token')
            && $request['grant_type'] === 'authorization_code' && $request['code'] === 'code-1' && $request['client_secret'] === 'ms-secret' && $request['redirect_uri'] === route('mail.outlook.callback'));
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
            && $request->hasHeader('Authorization', 'Bearer ms-access-1')
            && $request->hasHeader('Content-Type', 'text/plain')
            && str_contains((string) base64_decode($request->body()), 'Subject: CareerCraft test email'));
    });

    it('refuses a connection without permission to send, or without a refresh token', function (array $tokens): void {
        fakeMicrosoft($tokens);
        $user = User::factory()->create();

        outlookCallback($user)->assertSessionHas('error', 'Outlook was connected without permission to send email. Connect again and allow sending.');
        expect($user->mailAccount()->exists())->toBeFalse();
    })->with([
        'no send permission' => [['scope' => 'https://graph.microsoft.com/User.Read']],
        'no refresh token' => [['refresh_token' => null]],
    ]);

    it('keeps a working mail server when the Outlook test fails', function (): void {
        fakeMicrosoft(send: ['status' => 403, 'body' => ['error' => ['message' => 'The mailbox is either inactive, soft-deleted, or is hosted on-premise.']]]);
        $user = User::factory()->create();
        mailAccountFor($user);

        outlookCallback($user)->assertSessionHas('error', 'The email could not be sent. Outlook refused the email: The mailbox is either inactive, soft-deleted, or is hosted on-premise.');

        expect($user->mailAccount()->sole()->only(['provider', 'host', 'password']))->toBe(['provider' => 'smtp', 'host' => 'smtp.mailbox.example', 'password' => 'app-password']);
    });
});

describe('Sending through Outlook', function (): void {
    it('sends the application with its attachments, keeping the refresh token Microsoft hands out', function (): void {
        Storage::fake('local');
        fakeMicrosoft(['access_token' => 'ms-access-2', 'refresh_token' => 'ms-refresh-2']);
        $user = User::factory()->create();
        outlookAccount($user, ['token_expires_at' => now()->subMinute()]);
        $application = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'Bewerbung als Product Designer', 'emailBody' => 'Anbei meine Bewerbung.'])
            ->call('send')->assertHasNoErrors()->assertSee('Sent to jobs@nordwind.example.');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/oauth2/v2.0/token') && $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'ms-refresh-1');
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/me/sendMail')
            && $request->hasHeader('Authorization', 'Bearer ms-access-2')
            && str_contains((string) base64_decode($request->body()), 'filename=design-roles-version-2.pdf'));
        expect($user->mailAccount()->sole()->only(['access_token', 'refresh_token']))->toBe(['access_token' => 'ms-access-2', 'refresh_token' => 'ms-refresh-2']);
    });

    it('asks to connect again when Microsoft has taken the permission back', function (): void {
        Storage::fake('local');
        Http::fake(['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant'], 400), 'graph.microsoft.com/*' => Http::response()]);
        $user = User::factory()->create();
        outlookAccount($user, ['token_expires_at' => now()->subMinute()]);
        $application = recordedApplication($user);

        Livewire::actingAs($user)->test(Email::class, ['applicationId' => $application->id])
            ->set(['emailTo' => 'jobs@nordwind.example', 'emailSubject' => 'S', 'emailBody' => 'B'])->call('send')
            ->assertSee('The email could not be sent. Outlook no longer lets CareerCraft send for you.')
            ->assertDontSee('Your mail server said');

        expect($user->mailAccount()->sole()->verified_at)->toBeNull()
            ->and($application->emails()->count())->toBe(0);
        Livewire::actingAs($user)->test(MailAccountSettings::class)->assertSee(['Not connected', 'Outlook no longer lets CareerCraft send for you. Connect it again.', 'Connect Outlook again']);
    });
});
