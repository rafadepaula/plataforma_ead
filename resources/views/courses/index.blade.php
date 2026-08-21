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

    {{-- Tabela — visível a partir de `md`; abaixo disso a lista de cards assume. --}}
    <div class="d-none d-md-block">
        <x-ui.data-table striped hover responsive :headers="['Título', 'Carga horária', 'Alunos', 'Status', 'Ações']">
            @forelse($courses as $course)
                <tr dusk="course-row-{{ $course->id }}">
                    <td>
                        <div class="fw-semibold">{{ $course->title }}</div>
                        <div class="small text-body-secondary">
                            {{ $course->modules_count }} {{ Str::plural('módulo', $course->modules_count) }}
                            &middot;
                            {{ $course->lessons_count }} {{ Str::plural('aula', $course->lessons_count) }}
                        </div>
                    </td>
                    <td>{{ $course->workload_hours }} {{ Str::plural('hora', $course->workload_hours) }}</td>
                    <td>{{ $course->students_count }}</td>
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

                            <x-ui.button variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#delete-course-{{ $course->id }}"
                                         dusk="delete-course-{{ $course->id }}">Remover</x-ui.button>
                        </div>

                        <x-ui.confirm-modal id="delete-course-{{ $course->id }}"
                                            title="Remover Curso"
                                            :action="route('courses.destroy', $course)"
                                            method="DELETE"
                                            confirm-label="Remover"
                                            :message="'Remover “'.$course->title.'” é uma ação permanente. Cursos com matrículas ativas não podem ser removidos.'"
                                            dusk="delete-form-{{ $course->id }}" />
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
    </div>

    {{-- Lista de cards — abaixo de `md`, substitui a tabela (mesmo conteúdo, sem `dusk` duplicado). --}}
    <div class="d-md-none d-flex flex-column gap-3">
        @forelse($courses as $course)
            <x-ui.card>
                <x-slot:kickerSlot>
                    <x-ui.badge :variant="$course->is_published ? 'accent' : 'neutral'">
                        {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                    </x-ui.badge>
                </x-slot:kickerSlot>

                <x-slot:titleSlot>{{ $course->title }}</x-slot:titleSlot>

                <p class="mb-0">
                    {{ $course->modules_count }} {{ Str::plural('módulo', $course->modules_count) }}
                    &middot;
                    {{ $course->lessons_count }} {{ Str::plural('aula', $course->lessons_count) }}
                    &middot;
                    {{ $course->workload_hours }} {{ Str::plural('hora', $course->workload_hours) }}
                    &middot;
                    {{ $course->students_count }} {{ Str::plural('aluno', $course->students_count) }}
                </p>

                <x-slot:metaSlot>
                    <div class="d-flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" href="{{ route('courses.modules.index', $course) }}">Módulos</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('courses.completion-rules.index', $course) }}">Regras</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('courses.edit', $course) }}">Editar</x-ui.button>
                        <x-ui.button variant="danger" data-bs-toggle="modal" data-bs-target="#delete-course-{{ $course->id }}">Remover</x-ui.button>
                    </div>
                </x-slot:metaSlot>
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="book-open" title="Nenhum Curso cadastrado." description="Crie o primeiro Curso da sua Organização para começar a montar módulos e lições.">
                <x-slot:action>
                    <x-ui.button href="{{ route('courses.create') }}">Criar Curso</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </div>

    <x-ui.pagination :paginator="$courses" />
@endsection
