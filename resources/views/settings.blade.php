@php
    $user = auth()->user();
    $status = session('status');
    $twoFactorOn = $user->two_factor_confirmed_at !== null;
    $twoFactorPending = $user->two_factor_secret !== null && ! $twoFactorOn;
    $providers = \App\Http\Controllers\SocialLoginController::enabled();
    $connected = $user->socialAccounts->pluck('provider')->all();
@endphp

<x-layouts.app :title="__('Settings')">
    <h1 class="text-3xl font-semibold">{{ __('Settings') }}</h1>

    {{-- Fortify's own status codes are shown next to their sections; anything else is a message. --}}
    @if (is_string($status) && ! in_array($status, ['profile-information-updated', 'password-updated', 'two-factor-authentication-enabled', 'two-factor-authentication-confirmed', 'two-factor-authentication-disabled', 'recovery-codes-generated', 'verification-link-sent'], true))
        <p class="notice mt-6" role="status">{{ $status }}</p>
    @endif
    @if (session('error'))
        <p class="alert mt-6" role="alert">{{ session('error') }}</p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        {{-- Profile --}}
        <section class="card">
            <h2 class="card-title">{{ __('Profile') }}</h2>
            <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-4 flex flex-col gap-4">
                @csrf
                @method('PUT')
                <x-field name="name" :label="__('Name')" :value="$user->name" bag="updateProfileInformation" autocomplete="name" required />
                <x-field name="email" type="email" :label="__('Email')" :value="$user->email" bag="updateProfileInformation" autocomplete="email" required />
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('A new email address has to be confirmed again.') }}</p>
                @if ($status === 'profile-information-updated')
                    <p class="notice" role="status">{{ __('Saved.') }}</p>
                @endif
                <div><button type="submit" class="button button-primary">{{ __('Save') }}</button></div>
            </form>
        </section>

        {{-- Password --}}
        <section class="card">
            <h2 class="card-title">{{ __('Password') }}</h2>
            @if ($user->password === null)
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('You sign in with Google or GitHub. To also sign in with a password, request a link to set one.') }}
                </p>
                <form method="POST" action="{{ route('password.email') }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="email" value="{{ $user->email }}">
                    <button type="submit" class="button">{{ __('Email me a link to set a password') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('user-password.update') }}" class="mt-4 flex flex-col gap-4">
                    @csrf
                    @method('PUT')
                    <x-field name="current_password" type="password" :label="__('Current password')" bag="updatePassword" autocomplete="current-password" required />
                    <x-field name="password" type="password" :label="__('New password (at least 8 characters)')" bag="updatePassword" autocomplete="new-password" required />
                    <x-field name="password_confirmation" type="password" :label="__('Password again')" bag="updatePassword" autocomplete="new-password" required />
                    @if ($status === 'password-updated')
                        <p class="notice" role="status">{{ __('Password changed.') }}</p>
                    @endif
                    <div><button type="submit" class="button button-primary">{{ __('Change password') }}</button></div>
                </form>
            @endif
        </section>

        {{-- Two-factor login --}}
        <section class="card">
            <h2 class="card-title">{{ __('Two-factor login') }}</h2>

            @if ($twoFactorOn)
                <p class="mt-4 text-sm"><span class="badge">{{ __('On') }}</span> {{ __('You need a code from your authenticator app to sign in.') }}</p>

                @if (in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                    <div class="mt-4">
                        <p class="text-sm font-medium">{{ __('Save these recovery codes somewhere safe. Each one works once if you lose your phone.') }}</p>
                        <ul class="mt-2 grid grid-cols-2 gap-1 rounded-lg bg-zinc-100 p-3 font-mono text-sm dark:bg-zinc-800">
                            @foreach ($user->recoveryCodes() as $code)
                                <li>{{ $code }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap gap-3">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <button type="submit" class="button">{{ __('New recovery codes') }}</button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="button button-danger">{{ __('Turn off') }}</button>
                    </form>
                </div>
            @elseif ($twoFactorPending)
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Scan this with an authenticator app, then enter the code it shows.') }}</p>
                <div class="mt-4 inline-block rounded-lg bg-white p-3" role="img" aria-label="{{ __('QR code for your authenticator app') }}">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-field name="code" :label="__('Code from your app')" bag="confirmTwoFactorAuthentication" inputmode="numeric" autocomplete="one-time-code" required />
                    <button type="submit" class="button button-primary">{{ __('Turn on') }}</button>
                </form>
            @else
                <p class="mt-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Protect your account with a code from an authenticator app.') }}</p>
                <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="button button-primary">{{ __('Set up') }}</button>
                </form>
            @endif
        </section>

        {{-- Connected accounts --}}
        @if ($providers !== [])
            <section class="card">
                <h2 class="card-title">{{ __('Connected accounts') }}</h2>
                <ul class="mt-4 flex flex-col gap-3">
                    @foreach ($providers as $provider)
                        @php($label = $provider === 'github' ? 'GitHub' : 'Google')
                        <li class="flex items-center justify-between">
                            <span>{{ $label }}</span>
                            @if (in_array($provider, $connected, true))
                                <form method="POST" action="{{ route('social.disconnect', $provider) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button">{{ __('Disconnect') }}</button>
                                </form>
                            @else
                                <a href="{{ route('social.redirect', $provider) }}" class="button">{{ __('Connect') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <livewire:settings.devices />

        @php($usedToday = \App\Models\AiUsage::tokensToday(auth()->user()))
        @php($dailyLimit = \App\Ai\AiSettings::current()->dailyTokens)
        <section class="card">
            <h2 class="card-title">{{ __('AI use today') }}</h2>
            <p class="muted mt-2 text-sm">
                @if ($dailyLimit === 0)
                    {{ __(':used tokens so far. There is no daily limit.', ['used' => number_format($usedToday)]) }}
                @else
                    {{ __(':used of :limit tokens. The allowance starts again at midnight.', ['used' => number_format($usedToday), 'limit' => number_format($dailyLimit)]) }}
                @endif
            </p>
        </section>
    </div>
</x-layouts.app>
