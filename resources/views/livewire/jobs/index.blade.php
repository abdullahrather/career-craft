<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]" @if ($reading) wire:poll.3s @endif>
    <section aria-labelledby="your-jobs">
        <h2 id="your-jobs" class="sr-only">{{ __('Your job posts') }}</h2>
        @if ($posts->isEmpty())
            <p class="card muted">{{ __('No job posts yet. Paste your first one with the form.') }}</p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($posts as $post)
                    <li wire:key="job-{{ $post->id }}" class="card flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('jobs.show', ['id' => $post->id]) }}" class="card-title wrap-break-word hover:underline">
                                    {{ $post->title !== '' ? $post->title : (parse_url((string) $post->url, PHP_URL_HOST) ?: __('Job post')) }}
                                </a>
                                @if ($post->company !== '' || $post->location !== '')
                                    <p class="muted mt-1 text-sm">{{ implode(' · ', array_filter([$post->company, $post->location])) }}</p>
                                @endif
                            </div>
                            @if ($scores[$post->id] !== null)
                                <x-jobs.score :score="$scores[$post->id]" size="small" />
                            @endif
                        </div>
                        <p class="text-sm">
                            @if ($post->status === \App\Models\JobPost::READING)
                                <span class="muted inline-flex items-center gap-2" role="status">
                                    <span class="size-3 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                                    {{ __('Reading…') }}
                                </span>
                            @elseif ($post->status === \App\Models\JobPost::FAILED)
                                <span class="text-red-700 dark:text-red-400">{{ __('Could not be read.') }}</span>
                            @endif
                        </p>
                        <div class="mt-auto flex flex-wrap gap-2">
                            <a href="{{ route('jobs.show', ['id' => $post->id]) }}" class="button button-small">{{ __('Open') }}</a>
                            <button type="button" wire:click="delete({{ $post->id }})" wire:confirm="{{ __('Delete this job post?') }}" class="button button-danger button-small">{{ __('Delete') }}</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <form wire:submit="analyse" class="card flex flex-col gap-4" aria-labelledby="new-job">
        <h2 id="new-job" class="card-title">{{ __('Read a job post') }}</h2>
        <x-wire.textarea model="text" :label="__('Job post')" :hint="__('Copy the whole post from the job board and paste it here.')" rows="8" />
        <x-wire.input model="url" type="url" :label="__('Or its web address')" :hint="__('Some job boards can only be read by pasting the text.')" placeholder="https://" />
        <button type="submit" class="button button-primary self-start" wire:loading.attr="disabled">{{ __('Read the post') }}</button>
    </form>
</div>
