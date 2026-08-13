{{--
    Agrupador vertical dos campos de um formulário CRUD.

    Espaçamento: `<x-ui.input>`/`<x-ui.select>` já trazem `mb-3` no wrapper de
    cada campo. Por isso este componente NÃO aplica `gap-*` no modo coluna
    única — `gap` somaria ao `mb-3` e dobraria o respiro entre os campos.
    No modo duas colunas o `g-3` do grid é o gutter horizontal do Bootstrap
    (o vertical continua vindo do `mb-3` de cada campo).

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
        2 => 'row g-3',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>
