{{--
    Interruptor liga/desliga (`.form-switch` do Bootstrap) — efeito imediato,
    sem passo de confirmação. Usado em "Publicado" da lição e no consentimento
    do convite.

    Repopulação: mesma lógica do `<x-ui.checkbox>` — um switch desmarcado NÃO
    é enviado no request, então `old($name, $checked)` cai no default
    `$checked` quando o campo não veio no último submit. Tela que precisa
    distinguir "desmarcado pelo usuário" de "nunca enviado" emite um hidden
    com `value="0"` antes do componente.
--}}
@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'help' => null,
    'disabled' => false,
])

@php
    $id = $attributes->get('id', $name);
    $oldValue = old($name, $checked);
    $isChecked = $oldValue === true || (string) $oldValue === (string) $value;
    $hasError = isset($errors) && $errors->has($name);
    $errorId = $hasError ? "{$id}-error" : null;
    $describedBy = collect([$help ? "{$id}-help" : null, $errorId])->filter()->implode(' ') ?: null;
@endphp

<div class="form-check form-switch">
    <input type="checkbox"
           role="switch"
           id="{{ $id }}"
           name="{{ $name }}"
           value="{{ $value }}"
           @checked($isChecked)
           @disabled($disabled)
           @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->merge(['class' => 'form-check-input'.($hasError ? ' is-invalid' : '')]) }}>

    <label for="{{ $id }}" class="form-check-label">{{ $label }}</label>

    @if($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif

    @if($hasError)
        <div id="{{ $errorId }}" class="invalid-feedback d-block">{{ isset($errors) ? $errors->first($name) : '' }}</div>
    @endif
</div>
