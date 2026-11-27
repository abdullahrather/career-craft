<div class="grid gap-6 lg:grid-cols-2 lg:items-start">
    <div class="flex min-w-0 flex-col gap-6">
        <form wire:submit="save" class="card flex flex-col gap-4">
            <h2 class="card-title">{{ __('The job') }}</h2>
            <x-wire.input model="title" :label="__('Job title')" />
            <x-wire.input model="company" :label="__('Company')" />
            <x-wire.input model="location" :label="__('Place')" />
            <x-wire.input model="url" type="url" :label="__('Link to the job')" />
            <x-wire.input model="source" :label="__('Found on')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="field" for="status">
                    <span>{{ __('Status') }}</span>
                    <select id="status" wire:model="status">
                        @foreach ($statuses as $value => $text)
                            <option value="{{ $value }}">{{ $text }}</option>
                        @endforeach
                    </select>
                </label>
                <x-wire.input model="appliedOn" type="date" :label="__('Applied on')" />
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="button button-primary" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</button>
                @if ($application->jobPost !== null)
                    <a href="{{ route('jobs.show', ['id' => $application->job_post_id]) }}" class="link text-sm" wire:navigate>{{ __('The analysed job post') }}</a>
                @endif
                @if ($application->url !== null)
                    <a href="{{ $application->url }}" class="link text-sm" target="_blank" rel="noopener noreferrer nofollow">{{ __('The job online') }} ↗</a>
                @endif
            </div>
            @if (session('saved'))
                <p class="text-sm" role="status">{{ session('saved') }}</p>
            @endif
        </form>

        <livewire:applications.interviews :application-id="$application->id" />

        <livewire:applications.contacts :application-id="$application->id" />
    </div>

    <div class="flex min-w-0 flex-col gap-6">
        <section class="card flex flex-col gap-4" aria-labelledby="documents">
            <div>
                <h2 id="documents" class="card-title">{{ __('What you sent') }}</h2>
                <p class="muted mt-1 text-sm">{{ __('Record the CV version and letter you sent. A copy of each PDF stays with this application, even if the CV is deleted or the letter changed later.') }}</p>
            </div>

            @if ($application->cv_path !== null || $application->letter_path !== null)
                <ul class="flex flex-col gap-2 text-sm">
                    @if ($application->cv_path !== null)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="min-w-0 wrap-break-word"><span class="font-semibold">{{ __('CV') }}:</span> {{ $application->cv_name }}</span>
                            <a href="{{ route('applications.download', ['id' => $application->id, 'document' => 'cv']) }}" class="button button-small">{{ __('Download PDF') }}</a>
                        </li>
                    @endif
                    @if ($application->letter_path !== null)
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="min-w-0 wrap-break-word"><span class="font-semibold">{{ __('Letter') }}:</span> {{ $application->letter_name }}</span>
                            <a href="{{ route('applications.download', ['id' => $application->id, 'document' => 'letter']) }}" class="button button-small">{{ __('Download PDF') }}</a>
                        </li>
                    @endif
                </ul>
            @endif

            <form wire:submit="recordDocuments" class="flex flex-col gap-4">
                <label class="field" for="cvVersion">
                    <span>{{ __('CV version') }}</span>
                    <select id="cvVersion" wire:model="cvVersion">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($versions as $version)
                            <option value="{{ $version->id }}">{{ __(':name, version :number', ['name' => $version->name, 'number' => $version->number]) }} · {{ $version->created_at->translatedFormat('j M Y') }}</option>
                        @endforeach
                    </select>
                    @if ($versions->isEmpty())
                        <span class="field-hint">{{ __('A CV gets versions when you save one in the CV editor.') }}</span>
                    @endif
                    @error('cvVersion')
                        <span class="field-error" role="alert">{{ $message }}</span>
                    @enderror
                </label>
                <label class="field" for="letter">
                    <span>{{ __('Cover letter') }}</span>
                    <select id="letter" wire:model="letter">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($letters as $item)
                            <option value="{{ $item->id }}">{{ $item->subject !== '' ? $item->subject : ($item->language === 'de' ? __('Cover letter (German)') : __('Cover letter (English)')) }} · {{ $item->updated_at->translatedFormat('j M Y') }}</option>
                        @endforeach
                    </select>
                    @error('letter')
                        <span class="field-error" role="alert">{{ $message }}</span>
                    @enderror
                </label>
                <button type="submit" class="button self-start" wire:loading.attr="disabled" wire:target="recordDocuments">{{ __('Record what I sent') }}</button>
                @if (session('recorded'))
                    <p class="text-sm" role="status">{{ session('recorded') }}</p>
                @endif
            </form>
        </section>

        @if ($replies->isNotEmpty())
            <section class="card" aria-labelledby="replies">
                <h2 id="replies" class="card-title">{{ __('Replies') }}</h2>
                <ul class="mt-3 flex flex-col gap-3 text-sm">
                    @foreach ($replies as $reply)
                        <li wire:key="reply-{{ $reply->id }}">
                            <details>
                                <summary class="cursor-pointer">
                                    <span class="font-semibold wrap-break-word">{{ $reply->subject !== '' ? $reply->subject : __('(no subject)') }}</span>
                                    @if ($reply->kind !== null && $reply->kind !== 'other')
                                        <span class="badge badge-brand ms-1">{{ $kinds[$reply->kind] }}</span>
                                    @endif
                                    <span class="muted block text-xs">{{ $reply->from_name !== '' ? $reply->from_name.' <'.$reply->from_address.'>' : $reply->from_address }} · <time datetime="{{ $reply->received_at->toIso8601String() }}">{{ $reply->received_at->translatedFormat('j M Y, H:i') }}</time></span>
                                </summary>
                                <p class="mt-2 whitespace-pre-line wrap-break-word">{{ $reply->body }}</p>
                            </details>
                            <label class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                <span class="muted">{{ __('This email is') }}</span>
                                <select wire:change="relabel({{ $reply->id }}, $event.target.value)" class="rounded-lg border border-stone-300 bg-white px-2 py-1 dark:border-stone-700 dark:bg-stone-950">
                                    @if ($reply->kind === null)
                                        <option value="" selected disabled>{{ __('Not read yet') }}</option>
                                    @endif
                                    @foreach ($kinds as $value => $name)
                                        <option value="{{ $value }}" @selected($reply->kind === $value)>{{ $name }}</option>
                                    @endforeach
                                </select>
                                @if ($reply->corrected_at !== null)
                                    <span class="muted">{{ __('Labelled by you') }}</span>
                                @endif
                            </label>
                        </li>
                    @endforeach
                </ul>
                @if (session('relabelled'))
                    <p class="mt-3 text-sm" role="status">{{ session('relabelled') }}</p>
                @endif
            </section>
        @endif

        <livewire:applications.email :application-id="$application->id" />

        <livewire:applications.tasks :application-id="$application->id" />

        <livewire:applications.notes :application-id="$application->id" />

        <section class="card" aria-labelledby="timeline">
            <h2 id="timeline" class="card-title">{{ __('Timeline') }}</h2>
            <ol class="mt-3 flex flex-col gap-3 border-l-2 border-stone-200 pl-4 text-sm dark:border-stone-800">
                @foreach ($events as $event)
                    <li wire:key="event-{{ $event->id }}">
                        <p class="wrap-break-word">{{ $event->text() }}</p>
                        <p class="muted text-xs"><time datetime="{{ $event->created_at->toIso8601String() }}">{{ $event->created_at->translatedFormat('j M Y, H:i') }}</time></p>
                    </li>
                @endforeach
            </ol>
        </section>

        <button type="button" wire:click="delete" wire:confirm="{{ __('Delete this application and its copies?') }}" class="button button-danger self-start">{{ __('Delete') }}</button>
    </div>
</div>
