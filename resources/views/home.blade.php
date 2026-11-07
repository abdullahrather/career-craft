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

<x-layouts.app>
    <section class="py-16 sm:py-24">
        <span class="badge">{{ __('In development') }}</span>
        <h1 class="mt-6 max-w-3xl text-4xl font-semibold tracking-tight sm:text-6xl">
            {{ __('Your whole job search, from one career profile.') }}
        </h1>
        <p class="mt-6 max-w-2xl text-lg text-zinc-600 dark:text-zinc-400">
            {{ __('CareerCraft turns your profile into tailored CVs and cover letters, finds jobs that fit, tracks every application and helps you prepare for interviews.') }}
        </p>
        <div class="mt-10 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="button button-primary">{{ __('Go to your dashboard') }}</a>
            @else
                <a href="{{ route('register') }}" class="button button-primary">{{ __('Create a free account') }}</a>
                <a href="{{ route('login') }}" class="button">{{ __('Sign in') }}</a>
            @endauth
        </div>
    </section>

    <section class="py-12" aria-labelledby="how-it-works">
        <h2 id="how-it-works" class="text-2xl font-semibold">{{ __('How it works') }}</h2>
        <ol class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($steps as $index => [$title, $text])
                <li class="card">
                    <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ $index + 1 }}</span>
                    <h3 class="mt-2 font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="py-12" aria-labelledby="features">
        <h2 id="features" class="text-2xl font-semibold">{{ __('What it does') }}</h2>
        <ul class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as [$title, $text])
                <li class="card">
                    <h3 class="font-semibold">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $text }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="py-12" aria-labelledby="control">
        <div class="card bg-indigo-50 dark:bg-indigo-950/40">
            <h2 id="control" class="text-2xl font-semibold">{{ __('You stay in control') }}</h2>
            <ul class="mt-4 grid gap-3 text-zinc-700 sm:grid-cols-3 dark:text-zinc-300">
                <li>{{ __('Nothing is sent to an employer until you approve it.') }}</li>
                <li>{{ __('A free AI model can run on your own machine, so your CV never has to leave it.') }}</li>
                <li>{{ __('Built for the German job market, in English and German.') }}</li>
            </ul>
        </div>
    </section>
</x-layouts.app>
