@php
    $post = $item->jobPost;
    $reason = match (true) {
        $item->error === 'no-cv' => __('The CV to start from was deleted before this job was prepared.'),
        $item->error === 'prepare-failed' => __('Preparing this application failed.'),
        $post === null => __('The job post was deleted.'),
        $post->status === \App\Models\JobPost::FAILED => __('The job post could not be read.'),
        default => __('The tailored CV or the letter was deleted.'),
    };
@endphp

<div class="flex flex-col gap-6" @if ($state === 'preparing') wire:poll.5s @endif>
    <section class="card flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-display text-2xl font-semibold wrap-break-word">{{ $item->title }}</h1>
            @if ($item->company !== '')
                <p class="muted mt-1">{{ $item->company }}</p>
            @endif
            <p class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                @if ($match !== null && $match['score'] !== null)
                    <span class="badge">{{ __('Match :score%', ['score' => $match['score']]) }}</span>
                @endif
                @if ($post !== null)
                    <a href="{{ route('jobs.show', ['id' => $post->id]) }}" class="link" wire:navigate>{{ __('The analysed job post') }}</a>
                    @if ($post->url !== null)
                        <a href="{{ $post->url }}" class="link" target="_blank" rel="noopener noreferrer nofollow">{{ __('The job online') }} ↗</a>
                    @endif
                @endif
            </p>
        </div>
        @if (in_array($state, ['preparing', 'ready', 'failed'], true))
            <button type="button" wire:click="skip" class="button button-small">{{ __('Skip this job') }}</button>
        @endif
    </section>

    @if ($state === 'preparing')
        <section class="card flex items-start gap-4" role="status">
            <span class="mt-1 size-5 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
            <div>
                <p class="font-semibold">{{ __('Preparing this application…') }}</p>
                <p class="muted mt-1 text-sm">{{ __('The job post is read, then the AI tailors your CV and writes the letter. With the local AI this takes a few minutes. You can leave this page.') }}</p>
            </div>
        </section>
    @elseif ($state === 'failed')
        <p class="alert" role="alert">{{ $reason }} {{ __('Skip it, and queue the job again from Find jobs if you like.') }}</p>
    @elseif ($state === 'approved')
        <section class="card" role="status">
            <h2 class="card-title">{{ __('Approved') }}</h2>
            <p class="mt-2 text-sm">{{ __('It is in your applications, in Applied, with copies of the CV and letter. Now send them on the job\'s page.') }}</p>
            <p class="mt-4 flex flex-wrap gap-3">
                @if ($post?->url !== null)
                    <a href="{{ $post->url }}" class="button button-primary" target="_blank" rel="noopener noreferrer nofollow">{{ __('Apply on the job\'s page') }} ↗</a>
                @endif
                @if ($item->application_id !== null)
                    <a href="{{ route('applications.show', ['id' => $item->application_id]) }}" class="button" wire:navigate>{{ __('Open the application') }}</a>
                @endif
                <a href="{{ route('apply-queue') }}" class="button" wire:navigate>{{ __('Back to the queue') }}</a>
            </p>
        </section>
    @elseif ($state === 'skipped')
        <p class="notice" role="status">{{ __('Skipped. It stays in the log.') }}</p>
    @endif

    @if ($state === 'ready')
        <section class="card flex flex-col gap-3" aria-labelledby="approve-title">
            <h2 id="approve-title" class="card-title">{{ __('Approve') }}</h2>
            <p class="muted text-sm">{{ __('Approving saves the tailored CV as a version, and adds the job to your applications in Applied, with copies of the CV and letter. Nothing is sent to the employer.') }}</p>
            @if ($undecided > 0)
                <p class="text-sm">{{ trans_choice('{1} One suggestion below is neither accepted nor rejected; the CV keeps your own words there.|[2,*] :count suggestions below are neither accepted nor rejected; the CV keeps your own words there.', $undecided) }}</p>
            @endif
            @error('approve')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror
            <button type="button" wire:click="approve" wire:loading.attr="disabled" wire:target="approve" class="button button-primary self-start">{{ __('Approve') }}</button>
        </section>
    @endif

    @if (in_array($state, ['ready', 'approved'], true) && $item->answers_status !== null)
        @php
            $questions = ['why_company' => __('Why do you want to work at :company?', ['company' => $item->company !== '' ? $item->company : __('this company')]), 'why_fit' => __('Why are you a good fit for this role?')];
            $facts = array_filter([
                __('Salary expectation') => $profile->salary_expectation !== null ? __(':amount a year', ['amount' => \Illuminate\Support\Number::currency($profile->salary_expectation, 'EUR', app()->getLocale(), 0)]) : null,
                __('Notice period') => $profile->notice_period ?: null,
                __('Earliest start') => $profile->earliest_start?->translatedFormat('j F Y'),
                __('Work permit') => $profile->work_permit ?: null,
            ]);
        @endphp
        <section class="card flex flex-col gap-4" aria-labelledby="answers-title" @if ($item->answers_status === 'writing') wire:poll.5s @endif>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="answers-title" class="card-title">{{ __('Answers to common questions') }}</h2>
                    <p class="muted mt-1 text-sm">{{ __('Ready to paste into the application form. Read each one first: you send it, not CareerCraft.') }}</p>
                </div>
                @if ($item->answers_status !== 'writing')
                    <button type="button" wire:click="rewriteAnswers" wire:loading.attr="disabled" wire:target="rewriteAnswers" class="button button-small">{{ __('Write again') }}</button>
                @endif
            </div>
            @error('answers')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror

            @if ($item->answers_status === 'writing')
                <p class="flex items-center gap-2 text-sm" role="status">
                    <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                    {{ __('Writing the answers…') }}
                </p>
            @elseif ($item->answers_status === 'failed')
                <p class="field-error text-sm" role="alert">{{ __('The AI could not write the answers. Try again in a moment.') }}</p>
            @else
                @foreach ($item->answers ?? [] as $answer)
                    <div class="flex flex-col gap-2 border-t border-stone-200 pt-4 first:border-t-0 first:pt-0 dark:border-stone-800">
                        <div class="flex items-start justify-between gap-3">
                            <h3 class="font-semibold">{{ $questions[$answer['question']] ?? $answer['question'] }}</h3>
                            <x-copy-button :text="$answer['text']" :label="__('Copy the answer to: :question', ['question' => $questions[$answer['question']] ?? $answer['question']])" />
                        </div>
                        <p class="text-sm whitespace-pre-line wrap-break-word">{{ $answer['text'] }}</p>
                        @foreach ($answer['flags'] as $flag)
                            <p class="text-sm text-amber-800 dark:text-amber-300">{{ __('Not found in your profile: “:quote”. Check it before you use this answer.', ['quote' => $flag['quote']]) }}</p>
                        @endforeach
                        @if (! $answer['checked'])
                            <p class="text-sm text-amber-800 dark:text-amber-300">{{ __('This answer could not be checked against your profile. Read it carefully.') }}</p>
                        @endif
                    </div>
                @endforeach
            @endif

            <div class="border-t border-stone-200 pt-4 dark:border-stone-800">
                <h3 class="font-semibold">{{ __('From your profile') }}</h3>
                @if ($facts === [])
                    <p class="muted mt-1 text-sm">{{ __('Add your salary expectation, notice period, earliest start and work permit once, and they show here ready to copy.') }} <a href="{{ route('profile') }}#application" class="link">{{ __('Application details') }}</a></p>
                @else
                    <dl class="mt-2 flex flex-col gap-2 text-sm">
                        @foreach ($facts as $label => $value)
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="min-w-0"><dt class="muted inline">{{ $label }}:</dt> <dd class="inline wrap-break-word">{{ $value }}</dd></div>
                                <x-copy-button :text="$value" :label="__('Copy: :label', ['label' => $label])" />
                            </div>
                        @endforeach
                    </dl>
                    @if (count($facts) < 4)
                        <a href="{{ route('profile') }}#application" class="link mt-2 inline-block text-sm">{{ __('Add the rest in your career profile') }}</a>
                    @endif
                @endif
            </div>
        </section>
    @endif

    @if (in_array($state, ['ready', 'approved'], true) && $item->resume !== null && $item->coverLetter !== null)
        <livewire:resumes.tailoring :resume-id="$item->resume_id" :key="'tailoring-'.$item->resume_id" />
        <livewire:letters.editor :letter-id="$item->cover_letter_id" :key="'letter-'.$item->cover_letter_id" />
    @endif
</div>
