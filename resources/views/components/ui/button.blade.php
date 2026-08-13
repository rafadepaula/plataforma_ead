@props([
    'variant' => 'primary',
    'size' => 'md',
    'block' => false,
    'icon' => null,
    'type' => 'button',
    'href' => null,
    'disabled' => false,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-link text-body text-decoration-none',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    $classes = collect([
        'btn',
        $variantClass,
        $size === 'sm' ? 'btn-sm' : ($size === 'lg' ? 'btn-lg' : null),
        $block ? 'w-100' : null,
        $icon ? 'd-inline-flex align-items-center justify-content-start gap-2 text-start' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($disabled) class="disabled" aria-disabled="true" tabindex="-1" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-ui.icon :name="$icon" /> @endif
        {{ $slot }}
    </button>
@endif
