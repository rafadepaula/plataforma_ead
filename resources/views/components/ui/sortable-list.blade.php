{{--
    Lista reordenável por arraste (`[data-reorder-url]`) — o contrato DOM que
    `ModuleReorder.js` espera (usada por módulos e lições; a lista de questões
    do quiz continua no markup inline de `quizzes/partials/_question-list`).

    A tela passa a URL de persistência por prop e tudo mais (dusk, aria-label)
    por atributo — o componente é um widget puro de `ui`, não conhece rota.
--}}
@props([
    'reorderUrl',
])

<ul data-reorder-url="{{ $reorderUrl }}"
    role="list"
    {{ $attributes->merge(['class' => 'ds-sortable-list list-group list-unstyled m-0 p-0 d-flex flex-column gap-2']) }}>
    {{ $slot }}
</ul>
