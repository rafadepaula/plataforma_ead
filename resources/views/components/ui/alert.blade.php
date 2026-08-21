@props([
    'variant' => 'primary',
    'dismissable' => false,
])

@php
    // `danger`/`accent-2` deixam de resolver para `alert-danger` (vermelho do
    // Bootstrap padrão) e passam pelo par --critical-container/--on-critical-container,
    // igual a badge e chip — nenhuma matiz vermelha/laranja/amarela no sistema.
    // `secondary`/`light`/`dark` seguem o Bootstrap padrão: não fazem parte da
    // paleta proibida e não estão no conjunto de variantes alvo (01-direcao-
    // visual-e-tokens.md), então ficam como fallback preservado, sem remoção.
    $variantClass = match ($variant) {
        'danger', 'accent-2' => 'ds-tone-critical',
        'warning' => 'ds-tone-attention',
        'success' => 'ds-tone-success',
        'info' => 'ds-tone-info',
        'secondary' => 'alert-secondary',
        'light' => 'alert-light',
        'dark' => 'alert-dark',
        default => 'ds-tone-primary',
    };

    $iconName = match ($variant) {
        'danger', 'accent-2' => 'shield',
        'success' => 'check',
        default => 'info',
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
        <x-ui.icon :name="$iconName" size="20" />
    </div>

    <div class="flex-grow-1">
        {{ $slot }}
    </div>

    @if($dismissable)
        <button type="button"
                data-bs-dismiss="alert"
                class="btn-close flex-shrink-0"
                dusk="alert-dismiss"
                aria-label="Fechar alerta">
        </button>
    @endif
</div>
