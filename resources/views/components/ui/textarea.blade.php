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
    $hasError = $error || ($name && $errors->has($name));
    $describedBy = collect([
        $help ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3">
    @if($label)
        <label for="{{ $id }}" class="form-label">
            {{ $label }}
            @if($required) <span class="text-primary" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <textarea id="{{ $id }}"
              name="{{ $name }}"
              rows="{{ $rows }}"
              placeholder="{{ $placeholder }}"
              @required($required)
              @disabled($disabled)
              @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
              @if($hasError) aria-invalid="true" @endif
              {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }}>{{ old($name, $value) }}</textarea>

    @if($help)
        <div id="{{ $id }}-help" class="form-text">{{ $help }}</div>
    @endif

    @if($hasError)
        <div id="{{ $id }}-error" class="invalid-feedback" dusk="error-{{ $name }}">
            {{ $error ?? $errors->first($name) }}
        </div>
    @endif
</div>
