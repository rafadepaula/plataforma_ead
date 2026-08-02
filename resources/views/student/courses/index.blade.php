@extends('layouts.app')

{{--
    SPEC-07 RF19 — "Meus Cursos": the Aluno's own enrollments across every
    Organization they belong to, grouped by `org_id` (matches
    `StudentCourseController@index`'s
    `Auth::user()->courses()->with('organization')->get()->groupBy('org_id')`
    — intentionally NOT `OrgScope`-filtered, since a student may be enrolled
    in Courses from more than one Organization).
--}}

@section('content')
    <div style="margin-bottom: 20px;">
        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Área do Aluno</span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Meus Cursos</h1>
    </div>

    @forelse($courses as $orgId => $orgCourses)
        <div style="margin-bottom: 32px;" dusk="org-group-{{ $orgId }}">
            <h2 style="font-family: var(--font-heading); font-weight: 700; font-size: 16px; margin: 0 0 12px; color: var(--color-text);">
                {{ optional($orgCourses->first()->organization)->name ?? 'Organização' }}
            </h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
                @foreach($orgCourses as $course)
                    <x-ui.card :title="$course->title" dusk="student-course-{{ $course->id }}">
                        <p style="color: var(--color-neutral-600); margin: 0 0 12px;">{{ Str::limit($course->description, 80) }}</p>

                        <div style="background: var(--color-neutral-200); height: 8px; border-radius: 0px; overflow: hidden; margin-bottom: 12px;">
                            <div style="background: var(--color-accent); height: 100%; width: {{ (int) ($course->pivot->progress_percentage ?? 0) }}%;" dusk="progress-bar-{{ $course->id }}"></div>
                        </div>

                        <x-ui.button href="{{ route('classroom.show', $course) }}" size="sm" dusk="open-classroom-{{ $course->id }}">Acessar Curso</x-ui.button>
                    </x-ui.card>
                @endforeach
            </div>
        </div>
    @empty
        <p style="color: var(--color-neutral-600);" dusk="no-enrollments">Você ainda não está matriculado em nenhum curso.</p>
    @endforelse
@endsection
