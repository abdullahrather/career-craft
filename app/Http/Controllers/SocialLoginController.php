<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Sign in, or sign up, with Google or GitHub. A provider is offered only when the
 * runner has set its keys in .env.
 */
final class SocialLoginController
{
    /** @var list<string> */
    public const array PROVIDERS = ['google', 'github'];

    /** @return list<string> */
    public static function enabled(): array
    {
        return array_values(array_filter(
            self::PROVIDERS,
            fn (string $provider): bool => filled(config("services.{$provider}.client_id")),
        ));
    }

    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless(in_array($provider, self::enabled(), true), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::enabled(), true), 404);

        try {
            $remote = Socialite::driver($provider)->user();
        } catch (\Throwable) {
            return $this->failed(__('Signing in with :provider did not work. Please try again.', ['provider' => $this->label($provider)]));
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $remote->getId())
            ->first();
        $current = $request->user();

        // Signed in already: connect this provider to the account, from the settings page.
        if ($current instanceof User) {
            if ($account && $account->user_id !== $current->id) {
                return redirect()->route('settings')->with('error', __('That :provider account is already connected to another CareerCraft account.', ['provider' => $this->label($provider)]));
            }
            $current->socialAccounts()->firstOrCreate(['provider' => $provider], ['provider_id' => (string) $remote->getId()]);

            return redirect()->route('settings')->with('status', __(':provider is connected.', ['provider' => $this->label($provider)]));
        }

        $user = $account->user ?? $this->findOrCreateUser($provider, $remote);
        if (! $user instanceof User) {
            return $this->failed(__('An account with this email already exists. Sign in with your password, then connect :provider in your settings.', ['provider' => $this->label($provider)]));
        }

        return $this->signIn($request, $user);
    }

    /** Null when an account exists for the email but the provider can't vouch for it. */
    private function findOrCreateUser(string $provider, ProviderUser $remote): ?User
    {
        $email = $remote->getEmail();
        if (! is_string($email) || $email === '') {
            return null;
        }

        $user = User::query()->where('email', mb_strtolower($email))->first();
        if ($user instanceof User && ! $this->emailIsVerified($provider, $remote)) {
            return null;
        }

        $user ??= User::create([
            'name' => $remote->getName() ?: $email,
            'email' => mb_strtolower($email),
            'locale' => App::getLocale(),
        ]);
        if (! $user->hasVerifiedEmail() && $this->emailIsVerified($provider, $remote)) {
            $user->markEmailAsVerified();
        }
        $user->socialAccounts()->firstOrCreate(['provider' => $provider], ['provider_id' => (string) $remote->getId()]);

        return $user;
    }

    /** Google says whether it verified the address. GitHub only returns verified addresses. */
    private function emailIsVerified(string $provider, ProviderUser $remote): bool
    {
        if ($provider === 'github') {
            return true;
        }
        $raw = $remote instanceof \Laravel\Socialite\Two\User ? $remote->getRaw() : [];

        return ($raw['email_verified'] ?? false) === true;
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        // Two-factor login still applies: hand over to its challenge, as a password sign-in would.
        if ($user->two_factor_confirmed_at !== null) {
            $request->session()->put(['login.id' => $user->getKey(), 'login.remember' => false]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config()->string('fortify.home'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }

    private function label(string $provider): string
    {
        return $provider === 'github' ? 'GitHub' : 'Google';
    }
}
