@props([
    'name',
    'label' => null,
    'rows' => 4,
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'error' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $error || ($name && isset($errors) && $errors->has($name));
    $describedBy = collect([
        $help ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');

    // `.form-floating` só flutua o rótulo quando o controle tem um
    // `placeholder` não vazio (o CSS usa `:placeholder-shown`); quando a
    // tela não passa um, um espaço mantém o efeito sem exibir texto.
    $floatingPlaceholder = $placeholder !== null && $placeholder !== '' ? $placeholder : ' ';
@endphp

{{--
    Espaçamento: o wrapper carrega `mb-3` para telas que usam `<x-ui.textarea>`
    fora de um `<x-ui.field-stack>`. Dentro de um field-stack, a classe
    `ds-field` é zerada por `.ds-field-stack .ds-field` (`_floating-label.scss`)
    para não somar ao `--gap-stack` do `.ds-stack`/gutter do `.row.g-3` pai.
--}}
<div class="ds-field mb-3">
    <div class="form-floating">
        <textarea id="{{ $id }}"
                  name="{{ $name }}"
                  rows="{{ $rows }}"
                  placeholder="{{ $floatingPlaceholder }}"
                  @required($required)
                  @disabled($disabled)
                  @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
                  @if($hasError) aria-invalid="true" @endif
                  {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>

        @if($label)
            <label for="{{ $id }}">
                {{ $label }}
                @if($required) <span class="text-primary" aria-hidden="true">*</span>@endif
            </label>
        @endif

        @if($hasError)
            <div id="{{ $id }}-error" class="invalid-feedback d-flex align-items-center gap-1" dusk="error-{{ $name }}">
                <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
                <span>{{ $error ?? ($name && isset($errors) ? $errors->first($name) : '') }}</span>
            </div>
        @endif
    </div>

    @if($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif
</div>
