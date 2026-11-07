{{-- Only the providers the runner has set keys for. --}}
@php($providers = \App\Http\Controllers\SocialLoginController::enabled())

@if ($providers !== [])
    <div class="mt-6 flex flex-col gap-2">
        @foreach ($providers as $provider)
            <a href="{{ route('social.redirect', $provider) }}" class="button">
                {{ __('Continue with :provider', ['provider' => $provider === 'github' ? 'GitHub' : 'Google']) }}
            </a>
        @endforeach
    </div>
    <p class="mt-6 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('or with your email') }}</p>
@endif
