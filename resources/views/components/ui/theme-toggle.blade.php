@props([
    'size' => 'md',
])

@php
    $sizeClass = match ($size) {
        'sm' => 'btn-sm p-1',
        'lg' => 'btn-lg p-3',
        default => 'p-2',
    };
    $iconSize = match ($size) {
        'sm' => 16,
        'lg' => 22,
        default => 18,
    };
@endphp

<button type="button"
        data-theme-toggle
        dusk="theme-toggle"
        aria-label="Alternar tema"
        title="Alternar tema"
        {{ $attributes->merge(['class' => "btn btn-ghost ds-state-layer d-inline-flex align-items-center justify-content-center rounded-circle text-body {$sizeClass}"]) }}>
    {{-- Ícone de Lua (visível no modo claro, clica para ir ao escuro) --}}
    <span class="theme-icon-dark d-inline-flex align-items-center justify-content-center">
        <x-ui.icon name="moon" :size="$iconSize" aria-hidden="true" />
    </span>
    {{-- Ícone de Sol (visível no modo escuro, clica para ir ao claro) --}}
    <span class="theme-icon-light d-none d-inline-flex align-items-center justify-content-center">
        <x-ui.icon name="sun" :size="$iconSize" aria-hidden="true" />
    </span>
</button>
