@php
    $steps = [
        [__('Build your profile once'), __('Import your old CV or LinkedIn data, check it, and keep one complete record of your experience.')],
        [__('Tailor it to each job'), __('Paste a job post. Get a CV and cover letter that match it, in English or German.')],
        [__('Apply and keep track'), __('Send applications you have approved, and see every one on a single board.')],
        [__('Prepare for the interview'), __('Practise with a mock interviewer and get feedback on your answers.')],
    ];
    $features = [
        [__('Tailored CVs and letters'), __('Templates that applicant tracking systems can read, including the German Lebenslauf and Anschreiben.')],
        [__('AI that sticks to the facts'), __('Suggestions rephrase your real experience. Anything new is flagged until you confirm it.')],
        [__('Jobs that fit'), __('Openings from free public job sources, ranked by how well they match your profile.')],
        [__('One board for every application'), __('Which CV went where, contacts, notes and reminders to follow up.')],
        [__('Replies sorted for you'), __('Connect your inbox, and employer replies move your applications along by themselves.')],
        [__('Interview practice'), __('Questions for your role and the job, with scored feedback and a stronger example answer.')],
    ];
@endphp

<x-layouts.guest>
    <section class="grid items-center gap-12 py-10 lg:grid-cols-[1.1fr_0.9fr] lg:py-20">
        <div>
            <span class="badge badge-brand">{{ __('In development') }}</span>
            <h1 class="mt-6 text-4xl font-semibold text-stone-900 sm:text-6xl dark:text-white">
                {{ __('Your whole job search, from one career profile.') }}
            </h1>
            <p class="muted mt-6 max-w-xl text-lg">
                {{ __('CareerCraft turns your profile into tailored CVs and cover letters, finds jobs that fit, tracks every application and helps you prepare for interviews.') }}
            </p>
            <div class="mt-10 flex flex-wrap gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="button button-primary px-6 py-3 text-base">{{ __('Go to your dashboard') }}</a>
                @else
                    <a href="{{ route('register') }}" class="button button-primary px-6 py-3 text-base">{{ __('Create a free account') }}</a>
                    <a href="{{ route('login') }}" class="button px-6 py-3 text-base">{{ __('Sign in') }}</a>
                @endauth
            </div>
        </div>

        {{-- A sample tailored CV, so visitors see what the product makes. The person is fictional. --}}
        <div class="relative" aria-hidden="true">
            <div class="absolute -inset-4 -rotate-2 rounded-3xl bg-brand-100 dark:bg-brand-950"></div>
            <div class="card relative rotate-1 p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-display text-2xl font-semibold">Lena Fischer</p>
                        <p class="muted text-sm">{{ __('Backend developer · Hamburg') }}</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800 dark:bg-amber-950 dark:text-amber-300">{{ __('92% match') }}</span>
                </div>
                <div class="mt-6 space-y-2">
                    <p class="text-xs font-bold tracking-wider text-brand-700 uppercase dark:text-brand-300">{{ __('Experience') }}</p>
                    <p class="text-sm font-semibold">{{ __('Senior developer, Nordlicht Logistik') }}</p>
                    <ul class="muted list-disc space-y-1 pl-5 text-sm">
                        <li>{{ __('Cut order processing time by 40% with a new queue design') }}</li>
                        <li>{{ __('Led the move of four services to Laravel and PostgreSQL') }}</li>
                    </ul>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    @foreach (['PHP', 'Laravel', 'PostgreSQL', 'Redis', 'Docker'] as $skill)
                        <span class="badge">{{ $skill }}</span>
                    @endforeach
                </div>
                <p class="mt-6 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900 dark:bg-brand-950 dark:text-brand-200">
                    {{ __('Tailored for: Backend developer at Hafenblick Software') }}
                </p>
            </div>
        </div>
    </section>

    <section class="py-14" aria-labelledby="how-it-works">
        <h2 id="how-it-works" class="text-3xl font-semibold">{{ __('How it works') }}</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $index => [$title, $text])
                <li class="card">
                    <span class="flex size-9 items-center justify-center rounded-full bg-brand-700 font-display text-lg font-semibold text-white dark:bg-brand-500 dark:text-brand-950">{{ $index + 1 }}</span>
                    <h3 class="mt-4 font-semibold">{{ $title }}</h3>
                    <p class="muted mt-2 text-sm">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="py-14" aria-labelledby="features">
        <h2 id="features" class="text-3xl font-semibold">{{ __('What it does') }}</h2>
        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$title, $text])
                <li class="card">
                    <h3 class="font-semibold">{{ $title }}</h3>
                    <p class="muted mt-2 text-sm">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="py-14" aria-labelledby="control">
        <div class="rounded-3xl bg-brand-800 p-8 text-brand-50 sm:p-12 dark:bg-brand-900">
            <h2 id="control" class="text-3xl font-semibold text-white">{{ __('You stay in control') }}</h2>
            <ul class="mt-6 grid gap-6 sm:grid-cols-3">
                <li>{{ __('Nothing is sent to an employer until you approve it.') }}</li>
                <li>{{ __('A free AI model can run on your own machine, so your CV never has to leave it.') }}</li>
                <li>{{ __('Built for the German job market, in English and German.') }}</li>
            </ul>
        </div>
    </section>
</x-layouts.guest>
