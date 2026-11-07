<x-layouts.guest :title="__('Confirm your email')" narrow>
    <div class="card">
        <h1 class="text-2xl font-semibold">{{ __('Confirm your email') }}</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('We sent a link to :email. Open it to finish creating your account.', ['email' => auth()->user()?->email]) }}
        </p>

        @if (session('status') === 'verification-link-sent')
            <p class="notice mt-4" role="status">{{ __('A new link is on its way.') }}</p>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button type="submit" class="button">{{ __('Send the link again') }}</button>
        </form>
    </div>
</x-layouts.guest>
