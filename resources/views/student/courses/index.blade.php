@extends('layouts.app')

{{--
    "Meus Cursos": the Aluno's own enrollments across every
    Organization they belong to, grouped by `org_id` (matches
    `StudentCourseController@index`'s
    `Auth::user()->courses()->with('organization')->get()->groupBy('org_id')`
    — intentionally NOT `OrgScope`-filtered, since a student may be enrolled
    in Courses from more than one Organization).
--}}

@section('content')
    <x-layout.page-header kicker="Área do Aluno" title="Meus Cursos" />

    @forelse($courses as $orgId => $orgCourses)
        <div class="mb-8x" dusk="org-group-{{ $orgId }}">
            <h2 class="h5 mb-3x">
                {{ optional($orgCourses->first()->organization)->name ?? 'Organização' }}
            </h2>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4x">
                @foreach($orgCourses as $course)
                    <div class="col">
                        <x-ui.card :title="$course->title" class="h-100" dusk="student-course-{{ $course->id }}">
                            <p class="text-body-secondary mb-3x">{{ Str::limit($course->description, 80) }}</p>

                            <x-ui.progress
                                :value="(int) ($course->pivot->progress_percentage ?? 0)"
                                height="8"
                                label="Progresso do curso"
                                class="mb-3x"
                                dusk="progress-bar-{{ $course->id }}"
                            />

                            <x-ui.button href="{{ route('classroom.show', $course) }}" size="sm" dusk="open-classroom-{{ $course->id }}">Acessar Curso</x-ui.button>
                        </x-ui.card>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <x-ui.empty-state dusk="no-enrollments">
            Você ainda não está matriculado em nenhum curso.
        </x-ui.empty-state>
    @endforelse
@endsection
