@props([
    'id' => null,
    'name' => null,
    'title' => null,
    'dismissable' => true,
    'size' => 'md',
])

@php
    $modalId = $id ?? $name ?? 'modal-'.uniqid();
    $modalWidth = match($size) {
        'sm' => '400px',
        'lg' => '720px',
        default => '560px',
    };
    $showBinding = $name ? "{$name}" : "show";
@endphp

<div x-data="{ {{ $showBinding }}: false }"
     x-show="{{ $showBinding }}"
     x-cloak
     @keydown.escape.window="if ({{ $dismissable ? 'true' : 'false' }}) {{ $showBinding }} = false"
     class="dialog-backdrop"
     style="position: fixed; inset: 0; z-index: 100; display: none; align-items: center; justify-content: center; padding: 20px; background: color-mix(in srgb, var(--color-neutral-900) 65%, transparent); backdrop-filter: blur(2px);">
    
    <div id="{{ $modalId }}"
         class="dialog" 
         role="dialog" 
         aria-modal="true" 
         @click.outside="if ({{ $dismissable ? 'true' : 'false' }}) {{ $showBinding }} = false"
         style="width: {{ $modalWidth }}; max-width: 100%; background: var(--color-surface); border: 1px solid var(--color-divider); box-shadow: var(--shadow-lg); border-radius: 0px; display: flex; flex-direction: column; overflow: hidden;">
        
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid var(--color-divider);">
            <h3 class="dialog-title" style="font-family: var(--font-heading); font-weight: 800; font-size: 18px; margin: 0; color: var(--color-text);">
                {{ $title ?? 'Confirmação' }}
            </h3>

            @if($dismissable)
                <button type="button" 
                        @click="{{ $showBinding }} = false" 
                        data-modal-dismiss="true"
                        class="btn btn-ghost btn-icon" 
                        aria-label="Fechar" 
                        style="color: var(--color-text); border-radius: 0px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            @endif
        </div>

        <div class="dialog-body" style="padding: 24px; font-size: 14px; color: var(--color-text); overflow-y: auto; max-height: 70vh;">
            {{ $slot }}
        </div>

        @if(isset($actions))
            <div class="dialog-actions" style="padding: 16px 24px; background: color-mix(in srgb, var(--color-neutral-900) 4%, transparent); border-top: 1px solid var(--color-divider); display: flex; justify-content: flex-end; gap: 12px;">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
