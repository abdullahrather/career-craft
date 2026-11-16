@props(['score', 'size' => 'large'])

@php
    $tone = match (true) {
        $score >= 70 => 'bg-brand-100 text-brand-900 dark:bg-brand-900 dark:text-brand-100',
        $score >= 40 => 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
        default => 'bg-stone-100 text-stone-800 dark:bg-stone-800 dark:text-stone-200',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 flex-col items-center justify-center rounded-2xl font-semibold tabular-nums',
    $tone,
    'size-14 text-lg' => $size === 'small',
    'size-24 text-3xl' => $size !== 'small',
]) }} aria-label="{{ __('Match: :score %', ['score' => $score]) }}">
    <span aria-hidden="true">{{ $score }}<span class="text-[0.6em]">%</span></span>
</span>
