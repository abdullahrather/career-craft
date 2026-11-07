@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white text-zinc-900 antialiased">
    <header class="mx-auto flex max-w-5xl items-center justify-between px-4 py-6">
        <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>
        <x-language-switcher />
    </header>

    <main class="mx-auto max-w-5xl px-4">
        {{ $slot }}
    </main>
</body>
</html>
