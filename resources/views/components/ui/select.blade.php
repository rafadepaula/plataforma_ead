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
    $selectStyle = 'border-radius: 0px; width: 100%; height: 40px; padding: 8px 34px 8px 12px; font-size: 14px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text); appearance: none; -webkit-appearance: none; -moz-appearance: none; cursor: pointer;';
    if ($error || ($name && $errors->has($name))) {
        $selectStyle .= ' border-color: var(--color-accent);';
    }
@endphp

<div class="field" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
    @if($label)
        <label @if($name) for="{{ $name }}" @endif style="font-size: 13px; font-weight: 600; color: var(--color-text); display: block; text-align: left;">
            {{ $label }}
            @if($required) <span style="color: var(--color-accent);">*</span> @endif
        </label>
    @endif

    <div style="position: relative; width: 100%;">
        <select @if($name) id="{{ $name }}" name="{{ $name }}" @endif
                {{ $required ? 'required' : '' }}
                {{ $disabled ? 'disabled' : '' }}
                {{ $attributes->merge(['class' => 'input', 'style' => $selectStyle]) }}>
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

        <svg style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; opacity: 0.6;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square">
            <polyline points="6 9 12 15 18 9"></polyline>
        </svg>
    </div>

    @if($error)
        <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $error }}</span>
    @elseif($name && $errors->has($name))
        <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $errors->first($name) }}</span>
    @endif
</div>
