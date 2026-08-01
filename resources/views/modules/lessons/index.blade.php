@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">{{ $module->course->title }} / {{ $module->title }}</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Lições</h1>
        </div>

        <div style="display: flex; gap: 8px;">
            <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $module->course) }}">Voltar aos Módulos</x-ui.button>
            <x-ui.button href="{{ route('modules.lessons.create', $module) }}" dusk="new-lesson">Nova Lição</x-ui.button>
        </div>
    </div>

    <p style="font-size: 12px; color: var(--color-neutral-600); margin-bottom: 12px;">
        Arraste as lições para reordená-las. A nova ordem é salva automaticamente.
    </p>

    <ul data-reorder-url="{{ route('lessons.reorder', $module) }}"
        dusk="lesson-list"
        style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
        @forelse($lessons as $lesson)
            <li data-id="{{ $lesson->id }}"
                dusk="lesson-row-{{ $lesson->id }}"
                draggable="true"
                style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); cursor: grab;">
                <span style="display: flex; align-items: center; gap: 10px;">
                    <span aria-hidden="true" style="opacity: 0.5;">⠿</span>
                    {{ $lesson->title }}
                    <x-ui.badge variant="outline">{{ $lesson->type === 'quiz' ? 'Quiz' : 'Conteúdo' }}</x-ui.badge>
                </span>

                <span style="display: flex; gap: 8px;">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('lessons.edit', $lesson) }}" dusk="edit-lesson-{{ $lesson->id }}">Editar</x-ui.button>

                    <form method="POST" action="{{ route('lessons.destroy', $lesson) }}" dusk="delete-lesson-form-{{ $lesson->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" dusk="delete-lesson-{{ $lesson->id }}">Remover</button>
                    </form>
                </span>
            </li>
        @empty
            <li style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);">
                Nenhuma Lição cadastrada.
            </li>
        @endforelse
    </ul>
@endsection
