@props([
    'course',
])

{{-- O fórum é escopado a um curso: o único ponto de entrada dentro da
     sala de aula é este card (o item generalista do menu lateral saiu).
     O `dusk` fica no link clicável — alvo real do fluxo E2E. --}}
<x-ui.card title="Fórum de dúvidas" {{ $attributes }}>
    <p class="ds-caption text-secondary">
        Tire dúvidas sobre o conteúdo e acompanhe as discussões deste curso.
    </p>

    <x-ui.button variant="primary"
                 icon="message-square"
                 :href="route('forum.index', $course)"
                 dusk="classroom-forum-card">
        Acessar o fórum
    </x-ui.button>
</x-ui.card>
