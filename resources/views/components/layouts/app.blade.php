@props(['title' => null])

@php
    $links = [
        ['dashboard', __('Dashboard'), 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['profile', __('Career profile'), 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0'],
        ['resumes', __('CVs'), 'M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8zM14 3v5h5M9 13h6M9 17h6'],
        ['settings', __('Settings'), 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-2.9 1.2V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-2.9-1.2l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 3 14.1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.2-2.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1A1.7 1.7 0 0 0 10 3.1V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 2.9 1.2l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0 1.2 2.9h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z'],
    ];
@endphp

{{-- For signed-in people: a sidebar with the whole workflow, which collapses to a top bar on phones. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-screen bg-stone-50 text-stone-900 antialiased dark:bg-stone-950 dark:text-stone-100">
    <div class="lg:flex">
        <aside class="border-b border-stone-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-r lg:border-b-0 dark:border-stone-800 dark:bg-stone-900">
            <div class="flex items-center justify-between px-5 py-4 lg:py-6">
                <x-brand />
                <div class="flex items-center gap-2 lg:hidden">
                    <x-theme-toggle />
                </div>
            </div>

            <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-1 lg:flex-col lg:pb-0" aria-label="{{ __('Main') }}">
                @foreach ($links as [$route, $label, $icon])
                    <a href="{{ route($route) }}" class="nav-link shrink-0" @if (request()->routeIs($route, $route.'.*')) aria-current="page" @endif>
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            <div class="hidden border-t border-stone-200 p-4 lg:block dark:border-stone-800">
                <p class="truncate text-sm font-semibold">{{ auth()->user()?->name }}</p>
                <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ auth()->user()?->email }}</p>
                <div class="mt-4 flex items-center justify-between gap-2">
                    <x-language-switcher />
                    <x-theme-toggle />
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-stone-600 hover:underline dark:text-stone-400">{{ __('Sign out') }}</button>
                </form>
            </div>
        </aside>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 lg:px-10 lg:py-10">
            {{ $slot }}

            <div class="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 pt-6 lg:hidden dark:border-stone-800">
                <x-language-switcher />
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-sm font-semibold hover:underline">{{ __('Sign out') }}</button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
