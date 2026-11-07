@props(['name', 'label', 'type' => 'text', 'value' => null, 'bag' => 'default'])

@php($error = $errors->getBag($bag)->first($name))

<label class="field">
    <span>{{ $label }}</span>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes }}
    >
    @if ($error)
        <span id="{{ $name }}-error" class="field-error">{{ $error }}</span>
    @endif
</label>
