@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Gestão</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Cursos</h1>
        </div>

        <x-ui.button href="{{ route('courses.create') }}" dusk="new-course">Novo Curso</x-ui.button>
    </div>

    <x-ui.table :headers="['Título', 'Carga Horária', 'Status', 'Ações']">
        @forelse($courses as $course)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="course-row-{{ $course->id }}">
                <td style="padding: 12px 16px;">{{ $course->title }}</td>
                <td style="padding: 12px 16px;">{{ $course->workload_hours }}h</td>
                <td style="padding: 12px 16px;">
                    <x-ui.badge :variant="$course->is_published ? 'accent' : 'neutral'">
                        {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                    </x-ui.badge>
                </td>
                <td style="padding: 12px 16px; display: flex; gap: 8px;">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('courses.modules.index', $course) }}" dusk="manage-modules-{{ $course->id }}">Módulos</x-ui.button>
                    <x-ui.button variant="secondary" size="sm" href="{{ route('courses.edit', $course) }}" dusk="edit-course-{{ $course->id }}">Editar</x-ui.button>

                    <form method="POST" action="{{ route('courses.destroy', $course) }}" dusk="delete-form-{{ $course->id }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" dusk="delete-course-{{ $course->id }}">Remover</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                    Nenhum Curso cadastrado.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $courses->links() }}
    </div>
@endsection
