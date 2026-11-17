@php
    $post = $item->jobPost;
    $reason = match (true) {
        $item->error === 'no-cv' => __('The CV to start from was deleted before this job was prepared.'),
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

    @if (in_array($state, ['ready', 'approved'], true) && $item->resume !== null && $item->coverLetter !== null)
        <livewire:resumes.tailoring :resume-id="$item->resume_id" :key="'tailoring-'.$item->resume_id" />
        <livewire:letters.editor :letter-id="$item->cover_letter_id" :key="'letter-'.$item->cover_letter_id" />
    @endif
</div>
