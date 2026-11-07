@php
    $names = ['ollama' => __('Ollama on this computer'), 'anthropic' => 'Claude (Anthropic)', 'openai-compatible' => __('OpenAI-compatible API')];
    $notes = [
        'ollama' => __('Free, and nothing leaves this computer. Install Ollama and download a model, such as: ollama pull qwen2.5:7b'),
        'anthropic' => __('Paid, with your own key. Put ANTHROPIC_API_KEY in .env and restart the app.'),
        'openai-compatible' => __('Any service that speaks the OpenAI API, such as OpenAI itself, Mistral or LM Studio. Put OPENAI_COMPATIBLE_API_KEY in .env if it needs one.'),
    ];
    $suggestions = ['anthropic' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5']];
    $money = fn ($value) => '$'.number_format((float) $value, 4);
@endphp

<div class="grid grid-cols-[minmax(0,1fr)] items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
    <form wire:submit="save" class="card flex flex-col gap-5" aria-labelledby="ai-provider">
        <h2 id="ai-provider" class="card-title">{{ __('Provider') }}</h2>

        <fieldset class="flex flex-col gap-3">
            <legend class="sr-only">{{ __('Provider') }}</legend>
            @foreach ($names as $key => $label)
                <div wire:key="provider-{{ $key }}" class="rounded-xl border border-stone-300 p-3 has-checked:border-brand-600 has-checked:bg-brand-50 dark:border-stone-700 dark:has-checked:bg-brand-950">
                    <label class="flex cursor-pointer items-start gap-3">
                        <input type="radio" wire:model.live="provider" value="{{ $key }}" class="mt-1">
                        <span class="text-sm">
                            <span class="flex flex-wrap items-center gap-2 font-semibold">
                                {{ $label }}
                                <span @class(['badge', 'badge-brand' => $ready[$key]])>{{ $ready[$key] ? __('Ready') : __('Not set up') }}</span>
                            </span>
                            <span class="muted mt-1 block">{{ $notes[$key] }}</span>
                        </span>
                    </label>
                    @if ($provider === $key)
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="field">
                                <span>{{ __('Model') }}</span>
                                <input type="text" wire:model="models.{{ $key }}" list="models-{{ $key }}" maxlength="100">
                                <datalist id="models-{{ $key }}">
                                    @foreach ($key === 'ollama' ? $ollamaModels : ($suggestions[$key] ?? []) as $model)
                                        <option value="{{ $model }}">
                                    @endforeach
                                </datalist>
                                @if ($key === 'ollama')
                                    <span class="field-hint">{{ $ollamaModels === [] ? __('Ollama is not reachable right now.') : __('Installed: :models', ['models' => implode(', ', $ollamaModels)]) }}</span>
                                @endif
                                @error("models.{$key}") <span class="field-error">{{ $message }}</span> @enderror
                            </label>
                            @if ($key === 'openai-compatible')
                                <label class="field">
                                    <span>{{ __('Address of the API') }}</span>
                                    <input type="url" wire:model="compatibleUrl" placeholder="https://api.openai.com/v1">
                                    @error('compatibleUrl') <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                            @endif
                            <div class="grid grid-cols-2 gap-3 sm:col-span-2">
                                <label class="field">
                                    <span>{{ __('Input price') }}</span>
                                    <input type="number" step="0.01" min="0" wire:model="prices.{{ $key }}.0">
                                    @error("prices.{$key}.0") <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <label class="field">
                                    <span>{{ __('Output price') }}</span>
                                    <input type="number" step="0.01" min="0" wire:model="prices.{{ $key }}.1">
                                    @error("prices.{$key}.1") <span class="field-error">{{ $message }}</span> @enderror
                                </label>
                                <span class="field-hint col-span-2">{{ __('US dollars per million tokens, from your provider\'s price list. Used to track cost; 0 for free models.') }}</span>
                            </div>
                        </div>
                    @endif
                </div>
            @endforeach
            @error('provider') <p class="field-error text-sm">{{ $message }}</p> @enderror
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="field">
                <span>{{ __('Timeout') }}</span>
                <input type="number" min="10" max="180" wire:model="timeout">
                <span class="field-hint">{{ __('Seconds to wait for an answer, 10 to 180. Failed requests are tried up to three times.') }}</span>
                @error('timeout') <span class="field-error">{{ $message }}</span> @enderror
            </label>
            <label class="field">
                <span>{{ __('Daily allowance per person') }}</span>
                <input type="number" min="0" step="1" wire:model="dailyTokens">
                <span class="field-hint">{{ __('Tokens per person per day. 0 means no limit.') }}</span>
                @error('dailyTokens') <span class="field-error">{{ $message }}</span> @enderror
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="button button-primary">{{ __('Save') }}</button>
            @if ($saved)
                <span class="notice py-1.5" role="status">{{ __('Saved.') }}</span>
            @endif
        </div>
    </form>

    <div class="flex flex-col gap-6">
        <section class="card" aria-labelledby="ai-test">
            <h2 id="ai-test" class="card-title">{{ __('Test the provider') }}</h2>
            <p class="muted mt-1 text-sm">{{ __('Asks the saved provider for one sentence. The answer appears as it is written.') }}</p>
            <button type="button" wire:click="test" wire:loading.attr="disabled" wire:target="test" class="button mt-4">{{ __('Send a test') }}</button>
            <p wire:stream="answer" class="mt-4 min-h-6 rounded-xl bg-stone-50 px-3 py-2 text-sm dark:bg-stone-950" aria-live="polite">{{ $answer }}</p>
            @error('test') <p class="alert mt-3" role="alert">{{ $message }}</p> @enderror
        </section>

        <section class="card" aria-labelledby="ai-usage">
            <h2 id="ai-usage" class="card-title">{{ __('Use in the last 30 days') }}</h2>
            @if ($byModel->isEmpty())
                <p class="muted mt-2 text-sm">{{ __('No AI requests yet.') }}</p>
            @else
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="muted text-xs"><tr><th class="py-1 pr-3">{{ __('Model') }}</th><th class="pr-3 text-right">{{ __('Requests') }}</th><th class="pr-3 text-right">{{ __('Tokens') }}</th><th class="text-right">{{ __('Cost') }}</th></tr></thead>
                        <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                            @foreach ($byModel as $row)
                                <tr><td class="py-1.5 pr-3">{{ $row->model }} <span class="muted">({{ $names[$row->provider] ?? $row->provider }})</span></td><td class="pr-3 text-right">{{ number_format((int) $row->requests) }}</td><td class="pr-3 text-right">{{ number_format((int) $row->tokens) }}</td><td class="text-right">{{ $money($row->cost) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <table class="mt-6 w-full text-left text-sm">
                        <thead class="muted text-xs"><tr><th class="py-1 pr-3">{{ __('Person') }}</th><th class="pr-3 text-right">{{ __('Requests') }}</th><th class="pr-3 text-right">{{ __('Tokens') }}</th><th class="text-right">{{ __('Cost') }}</th></tr></thead>
                        <tbody class="divide-y divide-stone-200 dark:divide-stone-800">
                            @foreach ($byPerson as $row)
                                <tr><td class="py-1.5 pr-3">{{ $row->user?->name ?? __('Deleted account') }} <span class="muted">{{ $row->user?->email }}</span></td><td class="pr-3 text-right">{{ number_format((int) $row->requests) }}</td><td class="pr-3 text-right">{{ number_format((int) $row->tokens) }}</td><td class="text-right">{{ $money($row->cost) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</div>
