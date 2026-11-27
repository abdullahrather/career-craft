@php
    $groups = ['behavioural' => __('How you work'), 'role' => __('For this kind of role'), 'level' => __('For this level'), 'company' => __('About the company and the role')];
@endphp
<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('Practise for the interview') }}</h1>
        <p class="muted mt-1 text-sm">{{ __('Questions interviewers are likely to ask for :title at :company. Answer each one out loud: a short situation, what you did, and the result works best.', ['title' => $application->title, 'company' => $application->company !== '' ? $application->company : __('this company')]) }}</p>
        @if ($locale !== app()->getLocale())
            <p class="muted mt-1 text-sm">{{ __('The questions are in :language, the language of the job post.', ['language' => $locale === 'de' ? __('German') : __('English')]) }}</p>
        @endif
    </div>

    <section class="card flex flex-col gap-4" aria-labelledby="from-post" @if ($application->questions_status === 'writing') wire:poll.5s @endif>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="from-post" class="card-title">{{ __('About this job') }}</h2>
                <p class="muted mt-1 text-sm">{{ __('Written by the AI from the job post: its duties, the skills it asks for and its level.') }}</p>
            </div>
            @if ($analysed && $application->questions_status !== 'writing')
                <button type="button" wire:click="writeQuestions" wire:loading.attr="disabled" wire:target="writeQuestions" class="button button-small">{{ $application->questions_status === null ? __('Write questions') : __('Write again') }}</button>
            @endif
        </div>
        @error('questions')
            <p class="field-error text-sm" role="alert">{{ $message }}</p>
        @enderror

        @if (! $analysed)
            <p class="text-sm">{{ __('This application has no analysed job post, so there are no questions about the job itself.') }}</p>
        @elseif ($application->questions_status === 'writing')
            <p class="flex items-center gap-2 text-sm" role="status">
                <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                {{ __('Writing the questions…') }}
            </p>
        @elseif ($application->questions_status === 'failed')
            <p class="field-error text-sm" role="alert">{{ __('The AI could not write the questions. Try again in a moment.') }}</p>
        @endif
        @if ($analysed && $application->questions_status !== 'writing' && $application->questions !== null)
            <ol class="flex list-decimal flex-col gap-3 pl-5 text-sm">
                @foreach ($application->questions as $question)
                    <li>
                        <p class="wrap-break-word">{{ $question['question'] }}</p>
                        @if ($question['about'] !== '')
                            <p class="muted">{{ __('Tests: :about', ['about' => $question['about']]) }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif
    </section>

    @foreach ($bank as $group => $questions)
        <section class="card flex flex-col gap-3" aria-labelledby="group-{{ $group }}">
            <h2 id="group-{{ $group }}" class="card-title">{{ $groups[$group] }}</h2>
            <ol class="flex list-decimal flex-col gap-2 pl-5 text-sm">
                @foreach ($questions as $question)
                    <li class="wrap-break-word">{{ $question }}</li>
                @endforeach
            </ol>
        </section>
    @endforeach
</div>
