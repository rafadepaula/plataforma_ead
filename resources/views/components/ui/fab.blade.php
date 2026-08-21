{{--
    Ação de criação flutuante (56px, `--elev-3` + `--elev-primary`). Fica
    ancorado no canto da tela via `.ds-fab` (position: fixed) — não use dentro
    de um contêiner com `overflow: hidden`.

    `label` é obrigatória (sem default): o FAB só mostra ícone, então precisa
    de `aria-label` para leitores de tela (diretriz de acessibilidade e estados,
    "aria-label em todo botão só-ícone").
--}}
@props([
    'label',
    'icon' => 'plus',
    'href' => null,
    'type' => 'button',
])

@if ($href)
    <a href="{{ $href }}"
       aria-label="{{ $label }}"
       {{ $attributes->merge(['class' => 'ds-fab']) }}>
        <x-ui.icon :name="$icon" size="24" />
    </a>
@else
    <button type="{{ $type }}"
            aria-label="{{ $label }}"
            {{ $attributes->merge(['class' => 'ds-fab']) }}>
        <x-ui.icon :name="$icon" size="24" />
    </button>
@endif
