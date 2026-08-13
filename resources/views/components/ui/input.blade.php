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
    $hasError = $error || ($name && $errors->has($name));
    $describedBy = collect([
        $hint ? "{$id}-help" : null,
        $hasError ? "{$id}-error" : null,
    ])->filter()->implode(' ');
@endphp

<div class="mb-3">
    @if($kicker)
        <small class="text-uppercase fw-bold text-primary">{{ $kicker }}</small>
    @endif

    @if($label)
        <label @if($id) for="{{ $id }}" @endif class="form-label">
            {{ $label }}
            @if($required) <span class="text-primary">*</span> @endif
        </label>
    @endif

    @if($type === 'textarea')
        <textarea @if($id) id="{{ $id }}" @endif
                  @if($name) name="{{ $name }}" @endif
                  placeholder="{{ $placeholder }}"
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
               placeholder="{{ $placeholder }}"
               @required($required)
               @disabled($disabled)
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($hasError) aria-invalid="true" @endif
               {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }} />
    @endif

    @if($hint)
        <div @if($id) id="{{ $id }}-help" @endif class="form-text">{{ $hint }}</div>
    @endif

    @if($hasError)
        <div @if($id) id="{{ $id }}-error" @endif class="invalid-feedback" @if($name) dusk="error-{{ $name }}" @endif>
            {{ $error ?? ($name ? $errors->first($name) : '') }}
        </div>
    @endif
</div>
