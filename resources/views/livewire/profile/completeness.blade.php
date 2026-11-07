<section class="card" aria-labelledby="completeness-title">
    <div class="flex items-center gap-5">
        {{-- A ring that fills with the score. The number is also written out for screen readers. --}}
        <div class="relative size-20 shrink-0">
            <svg class="size-20 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3.5" class="stroke-stone-200 dark:stroke-stone-800" />
                <circle cx="18" cy="18" r="15.9" fill="none" stroke-width="3.5" stroke-linecap="round"
                    class="stroke-brand-600 transition-all dark:stroke-brand-400"
                    stroke-dasharray="{{ $score }} 100" pathLength="100" />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center font-display text-xl font-semibold">{{ $score }}%</span>
        </div>
        <div>
            <h2 id="completeness-title" class="card-title">{{ __('Profile strength') }}</h2>
            <p class="muted text-sm">
                @if ($score === 100)
                    {{ __('Complete. Every CV you make starts from a strong profile.') }}
                @else
                    {{ __('Your profile is :score% complete.', ['score' => $score]) }}
                @endif
            </p>
        </div>
    </div>

    @if ($next !== [])
        <h3 class="mt-5 text-sm font-semibold">{{ __('Next steps') }}</h3>
        <ul class="mt-2 space-y-2">
            @foreach (array_slice($next, 0, 4) as $step)
                <li>
                    <a href="#{{ $step['section'] }}" class="flex items-start gap-2 text-sm hover:underline">
                        <span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-500" aria-hidden="true"></span>
                        {{ $step['text'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
