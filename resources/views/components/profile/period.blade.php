@props(['from' => null, 'to' => null, 'ongoing' => null])

{{-- "Mar 2021 – today", in the visitor's language. --}}
@if ($from)
    <span {{ $attributes->merge(['class' => 'text-sm text-stone-500 dark:text-stone-400']) }}>
        {{ $from->translatedFormat('M Y') }} –
        {{ $to ? $to->translatedFormat('M Y') : ($ongoing ?? __('today')) }}
    </span>
@endif
