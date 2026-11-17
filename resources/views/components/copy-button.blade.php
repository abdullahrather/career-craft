@props(['text', 'label'])

{{-- Copies the text to the clipboard, and says so for two seconds. --}}
<button type="button" class="button button-small shrink-0" aria-label="{{ $label }}" data-text="{{ $text }}"
    x-data="{ copied: false }"
    x-on:click="navigator.clipboard.writeText($el.dataset.text).then(() => { copied = true; setTimeout(() => copied = false, 2000) })">
    <span x-show="! copied">{{ __('Copy') }}</span>
    <span x-show="copied" style="display: none" role="status">{{ __('Copied') }}</span>
</button>
