{{--
    Filtro rápido em formato pílula — substitui `<select>` de 2-3 opções
    (diretriz da biblioteca de componentes). Estado refletido
    via `aria-pressed`, nunca só por cor (diretriz de acessibilidade e estados).

    Uso típico — grupo de chips como filtro exclusivo/múltiplo de tela:
    <x-ui.chip :pressed="$status === 'active'" wire:click="filter('active')">
        Ativos
    </x-ui.chip>
--}}
@props([
    'pressed' => false,
    'icon' => null,
    'type' => 'button',
])

<button type="{{ $type }}"
        aria-pressed="{{ $pressed ? 'true' : 'false' }}"
        {{ $attributes->merge(['class' => 'ds-chip ds-state-layer']) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" size="16" class="ds-chip-icon" />
    @endif
    <span>{{ $slot }}</span>
</button>
