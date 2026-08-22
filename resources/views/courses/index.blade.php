{{--
    `/courses` (`courses.index`), reservada a `role:admin|gestor`
    (`OrgScope` confina a listagem à Organização do Gestor logado — ver
    `CourseController::index()`).

    `modules_count`/`lessons_count`/`students_count` vêm de `withCount()`
    (sem N+1 por linha) e alimentam a legenda "N módulos · N aulas" e a
    coluna "Alunos" — ver `CourseController::index()`.

    A remoção de Curso é a única desta tela que ganha `<x-ui.confirm-modal>`
    real (padrão espelhado de `admin/users/index.blade.php`): não há
    contrato de teste Dusk exigindo submit imediato aqui, diferente de
    `courses/enrollments/index.blade.php` e
    `courses/invitation-links/index.blade.php`.
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Gestão" title="Cursos" subtitle="Gerencie os cursos da sua Organização, seus módulos e as regras de conclusão.">
        <x-slot:actions>
            <x-ui.button href="{{ route('courses.create') }}" dusk="new-course">Novo Curso</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive :headers="['Título', 'Carga horária', 'Alunos', 'Status', 'Ações']">
        @forelse($courses as $course)
            <tr dusk="course-row-{{ $course->id }}">
                <td data-label="Título">
                    <div class="fw-semibold">{{ $course->title }}</div>
                    <div class="small text-body-secondary">
                        {{ $course->modules_count }} {{ Str::plural('módulo', $course->modules_count) }}
                        &middot;
                        {{ $course->lessons_count }} {{ Str::plural('aula', $course->lessons_count) }}
                    </div>
                </td>
                <td data-label="Carga horária" class="ds-tabular-nums">{{ $course->workload_hours }} {{ Str::plural('hora', $course->workload_hours) }}</td>
                <td data-label="Alunos" class="ds-tabular-nums">{{ $course->students_count }}</td>
                <td data-label="Status">
                    <x-ui.badge :variant="$course->is_published ? 'success' : 'neutral'">
                        {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                    </x-ui.badge>
                </td>
                <td data-label="Ações">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <x-ui.button variant="secondary" size="sm" href="{{ route('courses.modules.index', $course) }}" dusk="manage-modules-{{ $course->id }}">Módulos</x-ui.button>
                        <x-ui.button variant="secondary" size="sm" href="{{ route('courses.edit', $course) }}" dusk="edit-course-{{ $course->id }}">Editar</x-ui.button>
                        <x-ui.button variant="danger"
                                     size="sm"
                                     data-bs-toggle="modal"
                                     data-bs-target="#delete-course-{{ $course->id }}"
                                     dusk="delete-course-{{ $course->id }}">Remover</x-ui.button>

                        <div class="dropdown">
                            <x-ui.button variant="ghost"
                                         size="sm"
                                         id="course-actions-toggle-{{ $course->id }}"
                                         data-bs-toggle="dropdown"
                                         aria-expanded="false"
                                         aria-label="Mais ações para {{ $course->title }}">
                                Mais
                            </x-ui.button>
                            <div class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="course-actions-toggle-{{ $course->id }}">
                                <a class="dropdown-item d-flex align-items-center gap-2"
                                   href="{{ route('courses.completion-rules.index', $course) }}"
                                   dusk="manage-completion-rules-{{ $course->id }}">
                                    <x-ui.icon name="settings" size="16" aria-hidden="true" />
                                    <span>Regras de Conclusão</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="5" icon="book-open" title="Nenhum Curso cadastrado." description="Crie o primeiro Curso da sua Organização para começar a montar módulos e lições.">
                <x-slot:action>
                    <x-ui.button href="{{ route('courses.create') }}">Criar Curso</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </x-ui.data-table>

    @foreach($courses as $course)
        <x-ui.confirm-modal id="delete-course-{{ $course->id }}"
                            title="Remover Curso"
                            :action="route('courses.destroy', $course)"
                            method="DELETE"
                            confirm-label="Remover"
                            :message="'Remover “'.$course->title.'” é uma ação permanente. Cursos com matrículas ativas não podem ser removidos.'"
                            dusk="delete-form-{{ $course->id }}" />
    @endforeach

    <x-ui.pagination :paginator="$courses" />
@endsection
