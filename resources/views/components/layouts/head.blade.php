@props(['title' => null])

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
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
