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
    $inputStyle = 'border-radius: 0px; width: 100%; height: 40px; padding: 8px 12px; font-size: 14px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text);';
    if ($error || ($name && $errors->has($name))) {
        $inputStyle .= ' border-color: var(--color-accent);';
    }
@endphp

<div class="field" style="display: flex; flex-direction: column; gap: 6px; width: 100%;">
    @if($kicker)
        <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $kicker }}</span>
    @endif

    @if($label)
        <label @if($name) for="{{ $name }}" @endif style="font-size: 13px; font-weight: 600; color: var(--color-text); display: block; text-align: left;">
            {{ $label }}
            @if($required) <span style="color: var(--color-accent);">*</span> @endif
        </label>
    @endif

    @if($type === 'textarea')
        <textarea @if($name) id="{{ $name }}" name="{{ $name }}" @endif
                  placeholder="{{ $placeholder }}"
                  {{ $required ? 'required' : '' }}
                  {{ $disabled ? 'disabled' : '' }}
                  {{ $attributes->merge(['class' => 'input', 'style' => str_replace('height: 40px;', 'min-height: 90px;', $inputStyle)]) }}>{{ old($name, $value) }}</textarea>
    @else
        <input type="{{ $type }}"
               @if($name) id="{{ $name }}" name="{{ $name }}" @endif
               value="{{ old($name, $value) }}"
               placeholder="{{ $placeholder }}"
               {{ $required ? 'required' : '' }}
               {{ $disabled ? 'disabled' : '' }}
               {{ $attributes->merge(['class' => 'input', 'style' => $inputStyle]) }} />
    @endif

    @if($hint)
        <span style="font-size: 12px; color: var(--color-neutral-600);">{{ $hint }}</span>
    @endif

    @if($error)
        <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $error }}</span>
    @elseif($name && $errors->has($name))
        <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $errors->first($name) }}</span>
    @endif
</div>
