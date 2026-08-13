@props([
    'name',
    'label',
    'checked' => false,
    'value' => '1',
    'required' => false,
    'help' => null,
    'error' => null,
    'disabled' => false,
])

{{--
    Repopulação: um checkbox desmarcado NÃO é enviado no request, logo a chave
    some de old(). Por isso old($name, $checked) cai no default $checked quando
    o campo não veio no último submit — comportamento intencional e igual ao dos
    demais componentes de formulário. Telas que precisam distinguir "desmarcado
    pelo usuário" de "nunca enviado" devem emitir um hidden com value="0" antes
    do componente.
--}}
@php
    $id = $attributes->get('id', $name);
    $hasError = $error || ($name && $errors->has($name));
    $describedBy = collect([
        $help ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');

    $oldValue = old($name, $checked);
    $isChecked = $oldValue === true || (string) $oldValue === (string) $value;
@endphp

<div class="mb-3">
    <div class="form-check">
        <input type="checkbox"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ $value }}"
               @checked($isChecked)
               @required($required)
               @disabled($disabled)
               @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if($hasError) aria-invalid="true" @endif
               {{ $attributes->merge(['class' => 'form-check-input'.($hasError ? ' is-invalid' : '')]) }}>

        @if($hasError)
            <div id="{{ $id }}-error" class="invalid-feedback" dusk="error-{{ $name }}">
                {{ $error ?? $errors->first($name) }}
            </div>
        @endif

        <label for="{{ $id }}" class="form-check-label">
            {{ $label }}
            @if($required) <span class="text-primary" aria-hidden="true">*</span>@endif
        </label>

        @if($help)
            <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
        @endif
    </div>
</div>
