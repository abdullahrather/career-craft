@props(['title' => null, 'narrow' => false])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    {{-- Before anything paints, so the page never flashes the wrong theme. --}}
    <script>
        try {
            const saved = localStorage.getItem('theme');
            if (saved === 'dark' || (!saved && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        } catch {}
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col bg-zinc-50 text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <header class="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <nav class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-6 gap-y-3 px-4 py-4" aria-label="{{ __('Main') }}">
            <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>

            <div class="ml-auto flex flex-wrap items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium hover:underline">{{ __('Dashboard') }}</a>
                    <a href="{{ route('settings') }}" class="text-sm font-medium hover:underline">{{ __('Settings') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm font-medium hover:underline">{{ __('Sign out') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium hover:underline">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="button button-primary">{{ __('Create account') }}</a>
                @endauth
                <x-language-switcher />
                <button type="button" data-theme-toggle class="button px-2.5" aria-label="{{ __('Switch between light and dark theme') }}">
                    <svg class="size-4 dark:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                    <svg class="hidden size-4 dark:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                </button>
            </div>
        </nav>
    </header>

    <main @class(['mx-auto w-full flex-1 px-4 py-10', 'max-w-md' => $narrow, 'max-w-6xl' => ! $narrow])>
        {{ $slot }}
    </main>

    <footer class="border-t border-zinc-200 py-6 text-center text-sm text-zinc-500 dark:border-zinc-800 dark:text-zinc-400">
        {{ __('CareerCraft is in development. All people, companies and jobs in the demo are fictional.') }}
    </footer>
</body>
</html>
