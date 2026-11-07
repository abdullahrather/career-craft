@props(['model', 'label', 'type' => 'text', 'hint' => null])

@php($id = str_replace('.', '-', $model))

<label class="field" for="{{ $id }}">
    <span>{{ $label }}</span>
    <input
        id="{{ $id }}"
        type="{{ $type }}"
        wire:model="{{ $model }}"
        @error($model) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes }}
    >
    @if ($hint)
        <span class="field-hint">{{ $hint }}</span>
    @endif
    @error($model)
        <span id="{{ $id }}-error" class="field-error">{{ $message }}</span>
    @enderror
</label>
