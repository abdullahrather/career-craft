<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('Mock interview') }}</h1>
        <p class="muted mt-1 text-sm">{{ __('An AI interviewer asks the questions for :title one at a time and follows up on each answer. Type your answers as you would say them.', ['title' => $application->title]) }}</p>
    </div>

    @if ($interview === null)
        <section class="card flex flex-col items-start gap-3">
            <p class="text-sm">{{ __(':count questions, each with a follow-up on your answer. The interviewer doesn\'t judge your answers, so treat it like the real thing.', ['count' => $planned]) }}</p>
            <button type="button" wire:click="start" wire:loading.attr="disabled" class="button button-primary">{{ __('Start the interview') }}</button>
        </section>
    @else
        <section class="card flex flex-col gap-4" aria-labelledby="conversation">
            <h2 id="conversation" class="sr-only">{{ __('The conversation') }}</h2>
            <ol class="flex flex-col gap-3" aria-live="polite">
                @foreach ($interview->messages as $message)
                    <li wire:key="message-{{ $loop->index }}" @class(['max-w-[85%] rounded-2xl px-4 py-3 text-sm wrap-break-word', 'self-start bg-stone-100 dark:bg-stone-800' => $message['role'] === 'interviewer', 'self-end bg-brand-50 dark:bg-brand-950' => $message['role'] === 'candidate'])>
                        <span class="sr-only">{{ $message['role'] === 'interviewer' ? __('Interviewer:') : __('You:') }}</span>
                        <span class="whitespace-pre-line">{{ $message['text'] }}</span>
                    </li>
                @endforeach
                <li wire:loading.flex wire:target="reply, start, send" class="max-w-[85%] items-center gap-2 self-start rounded-2xl bg-stone-100 px-4 py-3 text-sm dark:bg-stone-800">
                    <span class="size-3 shrink-0 animate-pulse rounded-full bg-brand-600" aria-hidden="true"></span>
                    <span class="sr-only">{{ __('The interviewer is thinking…') }}</span>
                    {{-- Filled with the interviewer's words as they arrive. --}}
                    <span wire:stream="reply" class="whitespace-pre-line wrap-break-word"></span>
                </li>
            </ol>

            @error('reply')
                <p class="field-error text-sm" role="alert">{{ $message }}</p>
            @enderror
            @if ($due)
                <button type="button" wire:click="reply" wire:loading.remove wire:target="reply, start, send" class="button self-start">{{ __('Let the interviewer go on') }}</button>
            @endif

            @if ($interview->awaitsAnswer())
                <form wire:submit="send" class="flex flex-col gap-3" wire:loading.remove wire:target="reply, start, send">
                    <label class="field" for="answer">
                        <span>{{ __('Your answer') }}</span>
                        <textarea id="answer" wire:model="answer" rows="5" maxlength="{{ \App\Models\MockInterview::ANSWER_LENGTH }}" autofocus></textarea>
                        @error('answer')
                            <span class="field-error">{{ $message }}</span>
                        @enderror
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="button button-primary">{{ __('Send answer') }}</button>
                        <button type="button" wire:click="finish" wire:confirm="{{ __('End the interview now?') }}" class="button">{{ __('End the interview') }}</button>
                    </div>
                </form>
            @endif

            @if ($interview->finished_at !== null)
                <div class="flex flex-wrap items-center gap-3 border-t border-stone-200 pt-4 dark:border-stone-800">
                    <p class="text-sm" role="status">{{ __('The interview is over.') }}</p>
                    <button type="button" wire:click="start" wire:loading.attr="disabled" class="button button-small">{{ __('Start a new interview') }}</button>
                </div>
            @endif
        </section>
    @endif
</div>
