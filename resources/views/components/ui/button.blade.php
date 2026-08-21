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
    // `tonal` e `success` são valores novos (par container/on-container e
    // conclusão em menta); os demais preservam o nome mas mudam de classe.
    // `ghost` ganha borda obrigatória (`btn-ghost`, ver _state-layer.scss) —
    // a versão antiga (`btn-link`, sem borda) violava a regra transversal de
    // que nenhum botão fica sem contorno detectável.
    $variantClass = match ($variant) {
        'tonal' => 'btn-tonal ds-tone-primary ds-state-layer',
        'secondary' => 'btn-outline-secondary',
        'ghost' => 'btn-ghost ds-state-layer',
        'success' => 'btn-success',
        'danger' => 'btn-danger',
        default => 'btn-primary',
    };

    // `danger` nunca comunica gravidade só pela cor (diretriz de direção visual e tokens):
    // sempre acompanhado de `trash` ou `shield` + texto explícito no slot.
    // A prop `icon` continua autoritativa — isto só supre um default sensato
    // quando o call-site não escolheu um ícone.
    $iconName = $icon ?? ($variant === 'danger' ? 'trash' : null);

    $classes = collect([
        'btn',
        $variantClass,
        $size === 'sm' ? 'btn-sm' : ($size === 'lg' ? 'btn-lg' : null),
        $block ? 'w-100' : null,
        $iconName ? 'd-inline-flex align-items-center justify-content-start gap-2 text-start' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" @if ($disabled) class="disabled" aria-disabled="true" tabindex="-1" @endif {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconName) <x-ui.icon :name="$iconName" size="18" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @disabled($disabled) {{ $attributes->merge(['class' => $classes]) }}>
        @if ($iconName) <x-ui.icon :name="$iconName" size="18" /> @endif
        {{ $slot }}
    </button>
@endif
