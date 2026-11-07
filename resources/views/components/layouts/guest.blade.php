@props(['title' => null, 'narrow' => false])

{{-- For visitors: the landing page and the sign-in screens. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="flex min-h-screen flex-col bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    <header>
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-5" aria-label="{{ __('Main') }}">
            <x-brand />
            <div class="ml-auto flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="button button-primary">{{ __('Go to your dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold hover:underline">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="button button-primary">{{ __('Create account') }}</a>
                @endauth
                <x-language-switcher />
                <x-theme-toggle />
            </div>
        </nav>
    </header>

    <main @class(['mx-auto w-full flex-1 px-4 py-10', 'max-w-md' => $narrow, 'max-w-6xl' => ! $narrow])>
        {{ $slot }}
    </main>

    <footer class="py-8 text-center text-sm text-stone-500 dark:text-stone-400">
        {{ __('CareerCraft is in development. All people, companies and jobs in the demo are fictional.') }}
    </footer>
</body>
</html>
