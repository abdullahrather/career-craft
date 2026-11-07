<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

const PASSWORD = 'correct-horse-battery';

function member(array $attributes = []): User
{
    return User::factory()->create(['password' => Hash::make(PASSWORD), ...$attributes]);
}

describe('Signing up', function (): void {
    it('creates the account in the visitor\'s language and sends the confirmation email in it', function (): void {
        Notification::fake();

        $this->withHeader('Accept-Language', 'de')->post('/register', [
            'name' => 'Lena Fischer',
            'email' => 'lena@example.com',
            'password' => PASSWORD,
            'password_confirmation' => PASSWORD,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'lena@example.com')->firstOrFail();
        expect($user->locale)->toBe('de')
            ->and($user->hasVerifiedEmail())->toBeFalse();
        Notification::assertSentTo($user, VerifyEmail::class, fn ($notification, $channels, $notifiable, $locale) => $locale === 'de');
    });

    it('writes the confirmation email in German', function (): void {
        App::setLocale('de');

        $mail = (new VerifyEmail)->toMail(member(['email_verified_at' => null]));

        expect($mail->subject)->toBe('Bestätigen Sie Ihre E-Mail-Adresse')
            ->and($mail->actionText)->toBe('E-Mail-Adresse bestätigen');
    });

    it('keeps unconfirmed people out of the dashboard until they open the link', function (): void {
        $user = member(['email_verified_at' => null]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/email/verify');

        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $this->actingAs($user)->get($link);

        expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee($user->name);
    });

    it('shows German validation messages', function (): void {
        $this->withHeader('Accept-Language', 'de')
            ->from('/register')
            ->post('/register', ['name' => '', 'email' => 'not-an-email', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertSessionHasErrors([
                'name' => 'Name muss ausgefüllt werden.',
                'email' => 'E-Mail-Adresse muss eine gültige E-Mail-Adresse sein.',
            ]);
    });
});

describe('Signing in', function (): void {
    it('signs in with the right password and refuses a wrong one', function (): void {
        $user = member();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    });

    it('stops password guessing after five tries a minute', function (): void {
        $user = member();
        foreach (range(1, 5) as $try) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD]);

        $this->assertGuest();
    });
});

describe('Resetting a password', function (): void {
    it('answers an unknown email exactly like a real one', function (): void {
        Notification::fake();
        $user = member();

        $known = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->from('/forgot-password')->post('/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertSessionHas('status', __('passwords.sent'))->assertSessionHasNoErrors();
        $unknown->assertSessionHas('status', __('passwords.sent'))->assertSessionHasNoErrors();
    });

    it('sets a new password from the emailed link', function (): void {
        Notification::fake();
        $user = member();
        $this->post('/forgot-password', ['email' => $user->email]);

        $token = '';
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'a-brand-new-password',
            'password_confirmation' => 'a-brand-new-password',
        ])->assertRedirect('/login');

        $this->post('/login', ['email' => $user->email, 'password' => 'a-brand-new-password']);
        $this->assertAuthenticatedAs($user);
    });
});

describe('Two-factor login', function (): void {
    it('is set up with a code, then asked for at sign-in, and accepts a recovery code', function (): void {
        $user = member();
        $confirmed = ['auth.password_confirmed_at' => time()];

        $this->actingAs($user)->withSession($confirmed)->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $secret = decrypt((string) $user->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp(is_string($secret) ? $secret : '');

        $this->actingAs($user)->withSession($confirmed)
            ->post('/user/confirmed-two-factor-authentication', ['code' => $code])
            ->assertSessionHasNoErrors();
        expect($user->fresh()?->two_factor_confirmed_at)->not->toBeNull();

        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => PASSWORD])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $recovery = $user->fresh()?->recoveryCodes()[0];
        $this->post('/two-factor-challenge', ['recovery_code' => $recovery])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        expect($user->fresh()?->recoveryCodes())->not->toContain($recovery);
    });
});

describe('Language', function (): void {
    it('saves a signed-in person\'s choice to their account, for their emails', function (): void {
        $user = member();

        $this->actingAs($user)->from('/settings')->post('/locale', ['locale' => 'de'])->assertRedirect('/settings');

        expect($user->fresh()?->locale)->toBe('de');
    });

    it('uses a signed-in person\'s saved language over the browser', function (): void {
        $user = member(['locale' => 'de']);

        $this->actingAs($user)->withHeader('Accept-Language', 'en')->get('/dashboard')
            ->assertSee('<html lang="de">', escape: false);
    });
});

describe('Pages', function (): void {
    it('shows the landing page with sign-up for guests', function (): void {
        $this->get('/')->assertOk()->assertSee('Create a free account')->assertSee('How it works');
    });

    it('keeps the dashboard and settings for signed-in people', function (): void {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');

        $this->actingAs(member())->get('/settings')->assertOk()->assertSee('Two-factor login')->assertSee('Signed-in devices');
    });
});
