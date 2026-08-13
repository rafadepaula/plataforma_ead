@props([
    'variant' => 'accent',
])

@php
    $variantClass = match ($variant) {
        'outline' => 'border border-secondary text-body',
        'neutral' => 'text-bg-secondary',
        'accent-2' => 'text-bg-danger',
        default => 'text-bg-primary',
    };

    $classes = collect([
        'badge',
        $variantClass,
    ])->filter()->implode(' ');
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
