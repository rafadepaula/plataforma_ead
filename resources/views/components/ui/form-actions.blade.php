{{--
    Barra de ações do rodapé de um formulário: os botões vêm pelo slot default.
    Ancora à direita (`align="end"`) na maioria dos formulários CRUD — a tela
    escolhe via prop, o default aqui continua `start` para não mudar
    comportamento de call-sites existentes.

    A raiz separa-se dos campos com `mt-4 pt-3 border-top` (degraus do
    `$spacers` padrão do Bootstrap — o projeto só acrescenta as chaves
    `1x…8x`, não amplia a escala numérica). `flex-wrap` + `gap-2` aproximam
    `--gap-inline` (12px; o Bootstrap não tem um passo `g-*` exato para esse
    valor) e mantêm os botões legíveis quando quebram de linha no mobile.

    Uso:
    <x-ui.form-actions align="end" dusk="user-form-actions">
        <x-ui.button variant="ghost" :href="route('admin.users.index')">Cancelar</x-ui.button>
        <x-ui.button type="submit" dusk="user-submit">Salvar</x-ui.button>
    </x-ui.form-actions>
--}}
@props([
    'align' => 'start',
])

@php
    $justify = match ($align) {
        'end' => 'justify-content-end',
        'between' => 'justify-content-between',
        default => 'justify-content-start',
    };

    $classes = 'd-flex flex-wrap gap-2 mt-4 pt-3 border-top '.$justify;
@endphp

<div {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</div>
