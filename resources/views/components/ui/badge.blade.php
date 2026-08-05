@props([
    'variant' => 'accent',
])

@php
    $tagClass = match($variant) {
        'outline' => 'tag-outline',
        'neutral' => 'tag-neutral',
        'accent-2' => 'tag-accent-2',
        default => 'tag-accent',
    };

    $tagStyle = 'border-radius: 0px; display: inline-flex; align-items: center; padding: 3px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; border: 1px solid transparent;';
    if ($variant === 'accent') {
        $tagStyle .= ' background: var(--color-accent-100); color: var(--color-accent-700); border-color: var(--color-accent-300);';
    } elseif ($variant === 'outline') {
        $tagStyle .= ' background: transparent; color: var(--color-text); border-color: var(--color-divider);';
    } elseif ($variant === 'neutral') {
        $tagStyle .= ' background: var(--color-neutral-200); color: var(--color-neutral-800); border-color: var(--color-neutral-300);';
    } elseif ($variant === 'accent-2') {
        $tagStyle .= ' background: var(--color-accent-2-100); color: var(--color-accent-2-800); border-color: var(--color-accent-2-300);';
    }
@endphp

<span {{ $attributes->merge(['class' => "tag {$tagClass}", 'style' => $tagStyle]) }}>
    {{ $slot }}
</span>
