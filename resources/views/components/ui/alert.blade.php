@props([
    'variant' => 'primary',
    'dismissable' => false,
])

@php
    $variantClass = match ($variant) {
        'danger', 'accent-2' => 'alert-danger',
        'warning' => 'alert-warning',
        'success' => 'alert-success',
        'info' => 'alert-info',
        'secondary' => 'alert-secondary',
        'light' => 'alert-light',
        'dark' => 'alert-dark',
        default => 'alert-primary',
    };

    // `alert-dismissible` é deliberadamente omitido: ele posiciona o
    // `.btn-close` em absolute, o que brigaria com o layout flex abaixo.
    // `bootstrap.Alert` dispensa por completo — só o `data-bs-dismiss` importa.
    $classes = collect([
        'alert',
        $variantClass,
        $dismissable ? 'fade show' : null,
        'd-flex align-items-start gap-3',
    ])->filter()->implode(' ');
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}
     role="alert">
    <div class="flex-shrink-0 mt-1">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
    </div>

    <div class="flex-grow-1">
        {{ $slot }}
    </div>

    @if($dismissable)
        <button type="button"
                data-bs-dismiss="alert"
                class="btn-close flex-shrink-0"
                aria-label="Fechar alerta">
        </button>
    @endif
</div>
