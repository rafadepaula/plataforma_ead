@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => 'Selecione uma opção',
    'error' => null,
    'required' => false,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $error || ($name && $errors->has($name));
    $describedBy = collect([
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3">
    @if($label)
        <label @if($name) for="{{ $id }}" @endif class="form-label">
            {{ $label }}
            @if($required) <span class="text-primary" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <select @if($name) id="{{ $id }}" name="{{ $name }}" @endif
            @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if($hasError) aria-invalid="true" @endif
            @required($required)
            @disabled($disabled)
            {{ $attributes->merge(['class' => 'form-select'.($hasError ? ' is-invalid' : '')]) }}>
        @if($placeholder)
            <option value="" disabled {{ is_null(old($name, $selected)) ? 'selected' : '' }}>{{ $placeholder }}</option>
        @endif

        @foreach($options as $val => $optLabel)
            <option value="{{ $val }}" {{ (string)old($name, $selected) === (string)$val ? 'selected' : '' }}>
                {{ $optLabel }}
            </option>
        @endforeach

        {{ $slot }}
    </select>

    @if($error)
        <div id="{{ $id }}-error" class="invalid-feedback" dusk="error-{{ $name }}">{{ $error }}</div>
    @elseif($name && $errors->has($name))
        <div id="{{ $id }}-error" class="invalid-feedback" dusk="error-{{ $name }}">{{ $errors->first($name) }}</div>
    @endif
</div>
