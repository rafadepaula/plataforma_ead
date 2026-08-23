@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'error' => null,
    'required' => false,
    'disabled' => false,
    'kicker' => null,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
    $hasError = $error || ($name && isset($errors) && $errors->has($name));
    $describedBy = collect([
        $hint ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');

    // `.form-floating` só flutua o rótulo quando o controle tem um
    // `placeholder` não vazio (o CSS usa `:placeholder-shown`); quando a
    // tela não passa um, um espaço mantém o efeito sem exibir texto.
    $floatingPlaceholder = $placeholder !== null && $placeholder !== '' ? $placeholder : ' ';
@endphp

{{--
    Espaçamento: o wrapper carrega `mb-3` para telas que usam `<x-ui.input>`
    fora de um `<x-ui.field-stack>`. Dentro de um field-stack, a classe
    `ds-field` é zerada por `.ds-field-stack .ds-field` (`_floating-label.scss`)
    para não somar ao `--gap-stack` do `.ds-stack`/gutter do `.row.g-3` pai.
--}}
<div class="ds-field mb-3">
    @if($kicker)
        <small class="text-uppercase fw-bold text-primary d-block mb-1">{{ $kicker }}</small>
    @endif

    <div class="form-floating">
        @if($type === 'textarea')
            <textarea @if($id) id="{{ $id }}" @endif
                      @if($name) name="{{ $name }}" @endif
                      placeholder="{{ $floatingPlaceholder }}"
                      @required($required)
                      @disabled($disabled)
                      @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                      @if ($hasError) aria-invalid="true" @endif
                      {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>
        @else
            <input type="{{ $type }}"
                   @if($id) id="{{ $id }}" @endif
                   @if($name) name="{{ $name }}" @endif
                   value="{{ old($name, $value) }}"
                   placeholder="{{ $floatingPlaceholder }}"
                   @required($required)
                   @disabled($disabled)
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                   @if ($hasError) aria-invalid="true" @endif
                   {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }} />
        @endif

        @if($label)
            <label @if($id) for="{{ $id }}" @endif>
                {{ $label }}
                @if($required) <span class="text-primary" aria-hidden="true">*</span> @endif
            </label>
        @endif

        @if($hasError)
            <div @if($id) id="{{ $id }}-error" @endif class="invalid-feedback d-flex align-items-center gap-1" @if($name) dusk="error-{{ $name }}" @endif>
                <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
                <span>{{ $error ?? ($name && isset($errors) ? $errors->first($name) : '') }}</span>
            </div>
        @endif
    </div>

    @if($hint)
        <div @if($id) id="{{ $id }}-help" @endif class="form-text">{{ $hint }}</div>
    @endif
</div>
