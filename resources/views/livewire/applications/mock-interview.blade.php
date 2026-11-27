<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">{{ __('Mock interview') }}</h1>
        <p class="muted mt-1 text-sm">{{ __('An AI interviewer asks the questions for :title one at a time and follows up on each answer. Type your answers as you would say them.', ['title' => $application->title]) }}</p>
    </div>

    @if ($interview === null)
        <section class="card flex flex-col items-start gap-3">
            <p class="text-sm">{{ __(':count questions, each with a follow-up on your answer. The interviewer doesn\'t judge your answers, so treat it like the real thing. Afterwards the AI gives feedback on each answer.', ['count' => $planned]) }}</p>
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
                    <button type="submit" class="button button-primary self-start">{{ __('Send answer') }}</button>
                </form>
            @endif

            {{-- Also when the interviewer keeps failing, so it never blocks a new interview. --}}
            @if ($interview->finished_at === null)
                <button type="button" wire:click="finish" wire:confirm="{{ __('End the interview now?') }}" wire:loading.remove wire:target="reply, start, send" class="button self-start">{{ __('End the interview') }}</button>
            @endif

            @if ($interview->finished_at !== null)
                <div class="flex flex-wrap items-center gap-3 border-t border-stone-200 pt-4 dark:border-stone-800">
                    <p class="text-sm" role="status">{{ __('The interview is over.') }}</p>
                    <button type="button" wire:click="start" wire:loading.attr="disabled" class="button button-small">{{ __('Start a new interview') }}</button>
                </div>
            @endif
        </section>

        @if ($interview->finished_at !== null && $answers !== [])
            @php
                $parts = ['situation' => __('Situation'), 'task' => __('Task'), 'action' => __('Action'), 'result' => __('Result')];
                $reviewed = $interview->feedback ?? [];
            @endphp
            <section class="card flex flex-col gap-4" aria-labelledby="feedback" @if ($interview->feedback_status === 'writing') wire:poll.5s @endif>
                <div>
                    <h2 id="feedback" class="card-title">{{ __('Feedback') }}</h2>
                    <p class="muted mt-1 text-sm">{{ __('The AI scores each answer: how well it shows what the job asks for, from 1 to 5, and for a story, which parts of the STAR method it has: the situation, your task, what you did and the result.') }}</p>
                </div>

                @if ($reviewed !== [])
                    <p class="text-sm font-medium">{{ __('Fit to the job: :score of 5 on average over :count answers.', ['score' => number_format(collect($reviewed)->avg('fit'), 1), 'count' => count($reviewed)]) }}</p>
                @endif
                @if ($interview->feedback_status === 'writing')
                    <p class="flex items-center gap-2 text-sm" role="status">
                        <span class="size-4 shrink-0 animate-spin rounded-full border-2 border-brand-600 border-t-transparent" aria-hidden="true"></span>
                        {{ __('Writing the feedback: :done of :count answers…', ['done' => count($reviewed), 'count' => count($answers)]) }}
                    </p>
                @elseif ($interview->feedback_status === 'failed')
                    <p class="field-error text-sm" role="alert">{{ __('The AI could not write all of the feedback. Try again in a moment.') }}</p>
                @endif
                @error('feedback')
                    <p class="field-error text-sm" role="alert">{{ $message }}</p>
                @enderror
                @if ($interview->feedback_status !== 'writing' && $interview->feedback_status !== 'ready')
                    <button type="button" wire:click="writeFeedback" wire:loading.attr="disabled" wire:target="writeFeedback" class="button button-small self-start">{{ $interview->feedback_status === null ? __('Get feedback') : __('Try again') }}</button>
                @endif

                <ol class="flex flex-col gap-4">
                    @foreach ($reviewed as $item)
                        <li wire:key="feedback-{{ $item['index'] }}" class="flex flex-col gap-2 border-t border-stone-200 pt-4 text-sm dark:border-stone-800">
                            <h3 class="font-medium wrap-break-word">{{ $answers[$item['index']]['question'] ?? '' }}</h3>
                            <details>
                                <summary class="cursor-pointer">{{ __('Your answer') }}</summary>
                                <p class="muted mt-1 whitespace-pre-line wrap-break-word">{{ $answers[$item['index']]['answer'] ?? '' }}</p>
                            </details>
                            <ul class="flex flex-wrap gap-2">
                                <li class="rounded-full bg-stone-100 px-3 py-1 dark:bg-stone-800">{{ __('Fit to the job: :score of 5', ['score' => $item['fit']]) }}</li>
                                @if ($item['story'])
                                    @foreach ($parts as $part => $label)
                                        <li @class(['rounded-full px-3 py-1', 'bg-brand-50 dark:bg-brand-950' => $item['star'][$part], 'bg-stone-100 dark:bg-stone-800' => ! $item['star'][$part]])>
                                            {{ $item['star'][$part] ? __(':part: there', ['part' => $label]) : __(':part: missing', ['part' => $label]) }}
                                        </li>
                                    @endforeach
                                @endif
                            </ul>
                            <p class="whitespace-pre-line wrap-break-word">{{ $item['feedback'] }}</p>
                            <div>
                                <p class="font-medium">{{ __('A stronger answer') }}</p>
                                <p class="whitespace-pre-line wrap-break-word">{{ $item['example'] }}</p>
                                <p class="muted mt-1">{{ __('Built from your answer, but the AI may add details: keep only what is true for you, and fill in the parts in [brackets].') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    @endif
</div>
