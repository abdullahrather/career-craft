<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;

beforeEach(function (): void {
    config(['services.google.client_id' => 'test-client', 'services.github.client_id' => 'test-client']);
});

function providerUser(string $id, string $email, bool $verified = true): ProviderUser
{
    return (new ProviderUser)
        ->setRaw(['email_verified' => $verified])
        ->map(['id' => $id, 'name' => 'Jonas Weber', 'email' => $email]);
}

function returnsFromGoogle(ProviderUser $user): void
{
    Socialite::shouldReceive('driver->user')->andReturn($user);
}

it('signs up a new person with a confirmed email and signs them in', function (): void {
    returnsFromGoogle(providerUser('g-1', 'Jonas@Example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'jonas@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull()
        ->and($user->socialAccounts()->where('provider', 'google')->value('provider_id'))->toBe('g-1');
});

it('signs in the same person again by their Google account', function (): void {
    returnsFromGoogle(providerUser('g-1', 'jonas@example.com'));
    $this->get('/auth/google/callback');
    auth()->logout();

    returnsFromGoogle(providerUser('g-1', 'changed@example.com'));
    $this->get('/auth/google/callback');

    expect(User::query()->count())->toBe(1);
    $this->assertAuthenticated();
});

it('will not take over an existing account when Google has not confirmed the email', function (): void {
    User::factory()->create(['email' => 'jonas@example.com']);
    returnsFromGoogle(providerUser('g-2', 'jonas@example.com', verified: false));

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('will not link to an account whose email was never confirmed', function (): void {
    // Someone signed up with another person's email and a password they know, and never confirmed it.
    $squatter = User::factory()->create(['email' => 'jonas@example.com', 'email_verified_at' => null]);
    returnsFromGoogle(providerUser('g-4', 'jonas@example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($squatter->socialAccounts()->count())->toBe(0)
        ->and($squatter->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('finds an account whose email was typed with capitals at sign-up', function (): void {
    $this->post('/register', [
        'name' => 'Lena Fischer',
        'email' => 'Lena@Example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);
    $user = User::query()->sole();
    $user->markEmailAsVerified();
    auth()->logout();

    returnsFromGoogle(providerUser('g-5', 'lena@example.com'));
    $this->get('/auth/google/callback');

    expect($user->email)->toBe('lena@example.com')
        ->and(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs($user);
});

it('replaces the link when someone reconnects with a different account', function (): void {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'gh-old']);

    Socialite::shouldReceive('driver->user')->andReturn(providerUser('gh-new', $user->email));
    $this->actingAs($user)->get('/auth/github/callback')->assertSessionHas('status');

    expect($user->socialAccounts()->where('provider', 'github')->value('provider_id'))->toBe('gh-new');
});

it('still asks for the two-factor code', function (): void {
    $user = User::factory()->create(['email' => 'jonas@example.com', 'two_factor_confirmed_at' => now()]);
    returnsFromGoogle(providerUser('g-3', 'jonas@example.com'));

    $this->get('/auth/google/callback')->assertRedirect('/two-factor-challenge');

    $this->assertGuest();
    expect(session('login.id'))->toBe($user->id);
});

it('connects an account from the settings page, and refuses one already used elsewhere', function (): void {
    $other = User::factory()->create();
    $other->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'gh-9']);
    $user = User::factory()->create();

    Socialite::shouldReceive('driver->user')->andReturn(providerUser('gh-1', $user->email));
    $this->actingAs($user)->get('/auth/github/callback')->assertRedirect('/settings')->assertSessionHas('status');
    expect($user->socialAccounts()->pluck('provider')->all())->toBe(['github']);

    $third = User::factory()->create();
    Socialite::shouldReceive('driver->user')->andReturn(providerUser('gh-9', $third->email));
    $this->actingAs($third)->get('/auth/github/callback')->assertSessionHas('error');
    expect($third->socialAccounts()->count())->toBe(0);
});

it('keeps the last way to sign in from being disconnected', function (): void {
    $user = User::factory()->create(['password' => null]);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);

    $this->actingAs($user)->from('/settings')->delete('/settings/connections/google')->assertSessionHas('error');

    expect($user->socialAccounts()->count())->toBe(1);
});

it('hides providers without keys', function (): void {
    config(['services.google.client_id' => null]);

    $this->get('/auth/google/redirect')->assertNotFound();
    $this->get('/login')->assertDontSee('Continue with Google')->assertSee('Continue with GitHub');
});
