{{--
    Filtro rápido em formato pílula — substitui `<select>` de 2-3 opções
    (diretriz da biblioteca de componentes). Estado refletido
    via `aria-pressed`, nunca só por cor (diretriz de acessibilidade e estados).

    Props:
      - pressed bool (false)   estado ativo do filtro (só no modo interativo).
      - icon    string (null)  ícone opcional à esquerda do rótulo.
      - type    string ('button') tipo do `<button>` (só no modo interativo).
      - static  bool (false)   renderiza um `<span>` sem `aria-pressed` em vez
                                de um `<button>`. Use para chip de STATUS (ex.:
                                "Fixado" num card de tópico): um botão que não
                                submete nada vira um controle focável inútil na
                                ordem de teclado.
      - variant string (null)  tom do chip estático: `info` -> `.ds-chip-info`.

    Uso típico — grupo de chips como filtro exclusivo/múltiplo de tela:
    <x-ui.chip :pressed="$status === 'active'" wire:click="filter('active')">
        Ativos
    </x-ui.chip>

    Uso como chip de status não-interativo:
    <x-ui.chip :static="true" variant="info">Fixado</x-ui.chip>
--}}
@props([
    'pressed' => false,
    'icon' => null,
    'type' => 'button',
    'static' => false,
    'variant' => null,
])

@php
    $variantClass = match ($variant) {
        'info' => 'ds-chip-info',
        default => null,
    };

    $classes = collect([
        'ds-chip',
        $variantClass,
        $static ? 'ds-chip-static' : 'ds-state-layer',
    ])->filter()->implode(' ');
@endphp

@if ($static)
    <span {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" size="16" class="ds-chip-icon" />
        @endif
        <span>{{ $slot }}</span>
    </span>
@else
    <button type="{{ $type }}"
            aria-pressed="{{ $pressed ? 'true' : 'false' }}"
            {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" size="16" class="ds-chip-icon" />
        @endif
        <span>{{ $slot }}</span>
    </button>
@endif
