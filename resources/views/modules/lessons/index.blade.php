@extends('layouts.app')

@section('content')
    <x-layout.page-header :kicker="$module->course->title.' / '.$module->title" title="Lições">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $module->course) }}">Voltar aos Módulos</x-ui.button>
            <x-ui.button href="{{ route('modules.lessons.create', $module) }}" dusk="new-lesson">Nova Lição</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <p class="form-text mb-3">
        Arraste as lições para reordená-las. A nova ordem é salva automaticamente.
    </p>

    <ul data-reorder-url="{{ route('lessons.reorder', $module) }}"
        dusk="lesson-list"
        class="list-group list-unstyled m-0 p-0 d-flex flex-column gap-2">
        @forelse($lessons as $lesson)
            <li data-id="{{ $lesson->id }}"
                dusk="lesson-row-{{ $lesson->id }}"
                draggable="true"
                class="list-group-item sortable-item d-flex align-items-center justify-content-between gap-3">
                <span class="d-flex align-items-center gap-2">
                    <span aria-hidden="true" class="drag-handle">⠿</span>
                    {{ $lesson->title }}
                    <x-ui.badge variant="outline">{{ $lesson->type === 'quiz' ? 'Quiz' : 'Conteúdo' }}</x-ui.badge>
                </span>

                <span class="d-flex gap-2">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('lessons.edit', $lesson) }}" dusk="edit-lesson-{{ $lesson->id }}">Editar</x-ui.button>

                    <form method="POST" action="{{ route('lessons.destroy', $lesson) }}" dusk="delete-lesson-form-{{ $lesson->id }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="ghost" size="sm" dusk="delete-lesson-{{ $lesson->id }}">Remover</x-ui.button>
                    </form>
                </span>
            </li>
        @empty
            <li class="list-group-item border-dashed text-center text-body-secondary py-4">
                Nenhuma Lição cadastrada.
            </li>
        @endforelse
    </ul>
@endsection
