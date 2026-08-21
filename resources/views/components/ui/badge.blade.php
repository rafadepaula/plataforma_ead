@props([
    'variant' => 'accent',
    'size' => 'md',
    'dot' => true,
])

@php
    $variantClass = match ($variant) {
        'outline' => 'border ds-muted',
        'neutral' => 'ds-tone-neutral',
        'accent-2' => 'ds-tone-critical',
        'success' => 'ds-tone-success',
        'info' => 'ds-tone-info',
        default => 'ds-tone-primary',
    };

    $classes = collect([
        'badge',
        'ds-badge',
        $variantClass,
        $size === 'lg' ? 'ds-badge-lg' : null,
        $dot ? null : 'ds-badge-plain',
    ])->filter()->implode(' ');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
