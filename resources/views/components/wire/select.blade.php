@props(['model', 'label', 'options' => [], 'placeholder' => null])

@php($id = str_replace('.', '-', $model))

<label class="field" for="{{ $id }}">
    <span>{{ $label }}</span>
    <select id="{{ $id }}" wire:model="{{ $model }}" @error($model) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror {{ $attributes }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
    </select>
    @error($model)
        <span id="{{ $id }}-error" class="field-error">{{ $message }}</span>
    @enderror
</label>
