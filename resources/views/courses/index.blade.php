@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Gestão" title="Cursos">
        <x-slot:actions>
            <x-ui.button href="{{ route('courses.create') }}" dusk="new-course">Novo Curso</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive :headers="['Título', 'Carga Horária', 'Status', 'Ações']">
        @forelse($courses as $course)
            <tr dusk="course-row-{{ $course->id }}">
                <td>{{ $course->title }}</td>
                <td>{{ $course->workload_hours }}h</td>
                <td>
                    <x-ui.badge :variant="$course->is_published ? 'accent' : 'neutral'">
                        {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                    </x-ui.badge>
                </td>
                <td>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <x-ui.button variant="secondary" size="sm" href="{{ route('courses.modules.index', $course) }}" dusk="manage-modules-{{ $course->id }}">Módulos</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" href="{{ route('courses.completion-rules.index', $course) }}" dusk="manage-completion-rules-{{ $course->id }}">Regras de Conclusão</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" href="{{ route('courses.edit', $course) }}" dusk="edit-course-{{ $course->id }}">Editar</x-ui.button>

                        <form method="POST" action="{{ route('courses.destroy', $course) }}" class="d-inline" dusk="delete-form-{{ $course->id }}">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm" class="text-danger link-danger" dusk="delete-course-{{ $course->id }}">Remover</x-ui.button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="4" message="Nenhum Curso cadastrado." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$courses" />
@endsection
