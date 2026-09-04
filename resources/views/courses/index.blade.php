@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Gestão"
                          title="Cursos"
                          subtitle="Gerencie os cursos da sua Organização, seus módulos e as regras de conclusão.">
        <x-slot:actions>
            <x-ui.button icon="plus"
                         :href="route('courses.create')"
                         dusk="new-course">Novo curso</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.filter-bar :action="route('courses.index')"
                     :reset-url="route('courses.index')"
                     label="Filtros de cursos"
                     reset-label="Limpar filtros"
                     id="courses-filter-form">
        <div class="col-12 col-lg">
            <x-ui.input name="search"
                        label="Buscar por título"
                        :value="$search" />
        </div>

        <x-slot:actions>
            <div class="col-12 col-lg-auto d-flex flex-wrap align-items-center gap-2" role="group" aria-label="Filtrar por status">
                <x-ui.chip type="submit"
                           name="status"
                           value="all"
                           :pressed="$status === 'all'">Todos</x-ui.chip>
                <x-ui.chip type="submit"
                           name="status"
                           value="published"
                           :pressed="$status === 'published'">Publicados</x-ui.chip>
                <x-ui.chip type="submit"
                           name="status"
                           value="draft"
                           :pressed="$status === 'draft'">Rascunhos</x-ui.chip>
                <x-ui.button variant="ghost"
                             :href="route('courses.index')"
                             id="courses-filter-reset">Limpar filtros</x-ui.button>
            </div>
        </x-slot:actions>
    </x-ui.filter-bar>

    {{-- Desktop Table (>= md) --}}
    <div class="d-none d-md-block">
        <x-ui.data-table striped
                         hover
                         class="course-catalog-table"
                         aria-label="Catálogo de cursos"
                         id="courses-table">
            <x-slot:header>
                <tr>
                    <th scope="col">Título</th>
                    <th scope="col" class="course-catalog-workload-column course-col-workload">Carga horária</th>
                    <th scope="col" class="course-catalog-students-column course-col-students text-end">Alunos</th>
                    <th scope="col" class="course-catalog-status-column course-col-status">Status</th>
                    <th scope="col" class="course-catalog-actions-column course-col-actions text-end">Ações</th>
                </tr>
            </x-slot:header>

            @forelse($courses as $course)
                <tr dusk="course-row-{{ $course->id }}">
                    <td data-label="Título">
                        <x-course.title-cell :course="$course" />
                    </td>
                    <td data-label="Carga horária" class="course-catalog-workload-column course-col-workload ds-tabular-nums">
                        {{ $course->workload_hours }} {{ Str::plural('hora', $course->workload_hours) }}
                    </td>
                    <td data-label="Alunos" class="course-catalog-students-column course-col-students text-end ds-tabular-nums">
                        {{ $course->students_count }}
                    </td>
                    <td data-label="Status" class="course-catalog-status-column course-col-status">
                        <x-ui.badge :variant="$course->is_published ? 'success' : 'neutral'">
                            {{ $course->is_published ? 'Publicado' : 'Rascunho' }}
                        </x-ui.badge>
                    </td>
                    <td data-label="Ações" class="course-catalog-actions-column course-col-actions">
                        <x-course.row-actions :course="$course"
                                              manage-modules-dusk="manage-modules-{{ $course->id }}"
                                              forum-dusk="course-forum-{{ $course->id }}"
                                              manage-completion-rules-dusk="manage-completion-rules-{{ $course->id }}"
                                              edit-course-dusk="edit-course-{{ $course->id }}"
                                              delete-course-dusk="delete-course-{{ $course->id }}" />
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="5"
                                  icon="book-open"
                                  title="Nenhum curso cadastrado"
                                  description="Crie o primeiro curso para começar a matricular alunos.">
                    <x-slot:action>
                        <x-ui.button icon="plus" :href="route('courses.create')">Criar curso</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            @endforelse
        </x-ui.data-table>
    </div>

    {{-- Mobile Cards (< md) --}}
    <div class="d-md-none d-flex flex-column gap-3 mb-4">
        @forelse($courses as $course)
            <x-course.card-row :course="$course" />
        @empty
            <x-ui.empty-state icon="book-open"
                              title="Nenhum curso cadastrado"
                              description="Crie o primeiro curso para começar a matricular alunos.">
                <x-slot:action>
                    <x-ui.button icon="plus" :href="route('courses.create')">Criar curso</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @endforelse
    </div>

    @foreach($courses as $course)
        @if ((int) $course->active_students_count === 0)
            <x-ui.confirm-modal id="delete-course-{{ $course->id }}"
                                title="Remover curso"
                                :action="route('courses.destroy', $course)"
                                method="DELETE"
                                confirm-label="Remover"
                                :message="'Remover “'.$course->title.'” é uma ação permanente. Cursos com matrículas ativas não podem ser removidos.'"
                                form-dusk="delete-form-{{ $course->id }}" />
        @endif
    @endforeach

    <x-ui.pagination :paginator="$courses" item-label="cursos" />
@endsection
