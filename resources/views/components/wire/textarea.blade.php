@props(['model', 'label', 'hint' => null, 'rows' => 4])

@php($id = str_replace('.', '-', $model))

<label class="field" for="{{ $id }}">
    <span>{{ $label }}</span>
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        wire:model="{{ $model }}"
        @error($model) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror
        {{ $attributes }}
    ></textarea>
    @if ($hint)
        <span class="field-hint">{{ $hint }}</span>
    @endif
    @error($model)
        <span id="{{ $id }}-error" class="field-error">{{ $message }}</span>
    @enderror
</label>
