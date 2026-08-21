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

{{--
    Espaçamento: o wrapper carrega `mb-3` para telas que usam `<x-ui.select>`
    fora de um `<x-ui.field-stack>`. Dentro de um field-stack, a classe
    `ds-field` é zerada por `.ds-field-stack .ds-field` (`_floating-label.scss`)
    para não somar ao `--gap-stack` do `.ds-stack`/gutter do `.row.g-3` pai.
--}}
<div class="ds-field mb-3">
    <div class="form-floating">
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

        @if($label)
            <label @if($name) for="{{ $id }}" @endif>
                {{ $label }}
                @if($required) <span class="text-primary" aria-hidden="true">*</span>@endif
            </label>
        @endif

        @if($error)
            <div id="{{ $id }}-error" class="invalid-feedback d-flex align-items-center gap-1" dusk="error-{{ $name }}">
                <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
                <span>{{ $error }}</span>
            </div>
        @elseif($name && $errors->has($name))
            <div id="{{ $id }}-error" class="invalid-feedback d-flex align-items-center gap-1" dusk="error-{{ $name }}">
                <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
                <span>{{ $errors->first($name) }}</span>
            </div>
        @endif
    </div>
</div>
