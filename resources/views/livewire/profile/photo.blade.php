<section id="photo" class="card scroll-mt-6" aria-labelledby="photo-title" x-data="photoCropper">
    <h2 id="photo-title" class="card-title">{{ __('Photo') }}</h2>
    <p class="muted mt-1 text-sm">{{ __('Optional. Usual on German CVs, and best left off for jobs in the UK, Ireland and the US. Choose a photo, then drag and zoom to frame it.') }}</p>

    <div class="mt-4 flex flex-wrap items-start gap-6">
        <div x-show="source" style="display: none" class="flex flex-col gap-3">
            <div
                class="relative touch-none overflow-hidden rounded-xl bg-stone-100 outline-brand-600 focus-visible:outline-2 dark:bg-stone-800"
                :style="`width: ${width}px; height: ${height}px`"
                tabindex="0"
                role="img"
                aria-label="{{ __('Photo frame. Drag, or use the arrow keys, to move the photo.') }}"
                @pointerdown="start($event)" @pointermove="move($event)" @pointerup="end()" @pointercancel="end()"
                @keydown.arrow-left.prevent="nudge(10, 0)" @keydown.arrow-right.prevent="nudge(-10, 0)"
                @keydown.arrow-up.prevent="nudge(0, 10)" @keydown.arrow-down.prevent="nudge(0, -10)"
            >
                <img :src="source" alt="" draggable="false" class="pointer-events-none absolute max-w-none select-none" :style="imageStyle">
            </div>
            <label class="field">
                <span>{{ __('Zoom') }}</span>
                <input type="range" min="1" max="3" step="0.01" :value="zoom" @input="setZoom(+$event.target.value)">
            </label>
            <div class="flex gap-2">
                <button type="button" class="button button-primary button-small" @click="save()" :disabled="busy">{{ __('Use this photo') }}</button>
                <button type="button" class="button button-small" @click="cancel()" :disabled="busy">{{ __('Cancel') }}</button>
            </div>
    </div>

        <div x-show="! source" class="flex flex-wrap items-start gap-6">
            @if ($url)
                <img src="{{ $url }}" alt="{{ __('Your CV photo') }}" class="h-40 w-30 rounded-xl object-cover">
            @else
                <div class="flex h-40 w-30 items-center justify-center rounded-xl border border-dashed border-stone-300 text-center text-xs text-stone-500 dark:border-stone-700 dark:text-stone-400">{{ __('No photo') }}</div>
            @endif
            <div class="flex flex-col items-start gap-2">
                <label class="button button-small cursor-pointer focus-within:outline-2 focus-within:outline-brand-600">
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="choose($event)">
                    {{ $url ? __('Change photo') : __('Choose a photo') }}
                </label>
                @if ($url)
                    <button type="button" wire:click="remove" wire:confirm="{{ __('Remove your photo?') }}" class="button button-danger button-small">{{ __('Remove') }}</button>
                @endif
            </div>
        </div>
    </div>

    <p x-show="failed" style="display: none" class="field-error mt-3 text-sm" role="alert">{{ __('This picture could not be opened. Use a JPEG or PNG file.') }}</p>
    @error('photo')
        <p class="field-error mt-3 text-sm" role="alert">{{ $message }}</p>
    @enderror
</section>
