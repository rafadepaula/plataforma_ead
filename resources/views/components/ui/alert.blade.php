@props([
    'variant' => 'accent',
    'dismissable' => false,
    'icon' => null,
])

@php
    $alertStyle = 'border-radius: 0px; padding: 14px 18px; margin-bottom: 16px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; font-size: 14px; border: 1px solid transparent;';
    
    if ($variant === 'accent-2' || $variant === 'danger') {
        $alertStyle .= ' background: var(--color-accent-2-100); color: var(--color-accent-2-800); border-color: var(--color-accent-2-300);';
    } elseif ($variant === 'warning') {
        $alertStyle .= ' background: var(--color-neutral-200); color: var(--color-neutral-900); border-color: var(--color-neutral-400);';
    } else {
        $alertStyle .= ' background: var(--color-accent-100); color: var(--color-accent-800); border-color: var(--color-accent-300);';
    }
@endphp

<div x-data="{ show: true }" 
     x-show="show" 
     {{ $attributes->merge(['class' => 'alert', 'style' => $alertStyle]) }}>
    <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0; margin-top: 2px;">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>

        <div style="flex: 1;">
            {{ $slot }}
        </div>
    </div>

    @if($dismissable)
        <button type="button" 
                @click="show = false" 
                class="btn btn-ghost btn-icon" 
                aria-label="Fechar alerta"
                style="color: inherit; padding: 2px; border-radius: 0px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
        </button>
    @endif
</div>
