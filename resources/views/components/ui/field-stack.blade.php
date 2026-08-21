{{--
    Agrupador vertical dos campos de um formulário CRUD.

    Espaçamento: `--gap-stack` (20px) mora aqui. `<x-ui.input>`/`<x-ui.select>`/
    `<x-ui.textarea>` trazem seu próprio `mb-3` (classe `ds-field`) para
    continuar funcionando fora de um field-stack; `.ds-field-stack .ds-field`
    (`_floating-label.scss`) zera esse `mb-3` para não somar ao gap deste
    componente. Modo coluna única usa `.ds-stack`
    (`display:flex;flex-direction:column;gap:var(--gap-stack)`, de
    `_ds/.../tokens/primitives.css`). No modo duas colunas o `g-3` do grid
    Bootstrap dá o gutter horizontal **e** vertical (o `.row` com `g-*` já
    aplica `margin-top` a cada `.col` que quebra linha).

    Uso (1 coluna):
    <x-ui.field-stack dusk="user-fields">
        <x-ui.input name="name" label="Nome" />
        <x-ui.input name="email" label="E-mail" type="email" />
    </x-ui.field-stack>

    Uso (2 colunas) — a tela é quem coloca cada campo em sua `.col-md-6`:
    <x-ui.field-stack :columns="2" dusk="user-fields">
        <div class="col-md-6"><x-ui.input name="name" label="Nome" /></div>
        <div class="col-md-6"><x-ui.input name="cpf" label="CPF" /></div>
    </x-ui.field-stack>
--}}
@props([
    'columns' => 1,
])

@php
    $classes = match ((int) $columns) {
        2 => 'ds-field-stack row g-3',
        default => 'ds-field-stack ds-stack',
    };
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>
