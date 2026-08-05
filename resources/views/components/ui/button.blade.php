@props([
    'variant' => 'primary',
    'size' => 'md',
    'block' => false,
    'icon' => false,
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $baseClass = 'btn';
    $variantClass = match($variant) {
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        default => 'btn-primary',
    };
    
    $classes = collect([
        $baseClass,
        $variantClass,
        $block ? 'btn-block' : null,
        $icon ? 'btn-icon' : null,
    ])->filter()->implode(' ');

    $inlineStyle = 'border-radius: 0px; text-align: left; justify-content: flex-start;';
    if ($size === 'sm') {
        $inlineStyle .= ' padding: 6px 12px; font-size: 12px;';
    } elseif ($size === 'lg') {
        $inlineStyle .= ' padding: 14px 24px; font-size: 16px;';
    } else {
        $inlineStyle .= ' padding: 10px 18px; font-size: 14px;';
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes, 'style' => $inlineStyle]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $disabled ? 'disabled' : '' }} {{ $attributes->merge(['class' => $classes, 'style' => $inlineStyle]) }}>
        {{ $slot }}
    </button>
@endif
