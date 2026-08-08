@extends('layouts.app')

{{--
    SPEC-07 RF20 — Classroom entry point for a single Course: the Module /
    Lesson tree plus a progress bar bound to `course_user.progress_percentage`.

    Expected `ClassroomController@show` contract (Bucket 2):
      - `$course`             the bound Course.
      - `$modules`            Collection<Module> eager-loaded with `lessons`
                               (published only), ordered by `order_index`.
      - `$completedLessonIds` array<int> of this student's completed
                               `lesson_progress.lesson_id` for this Course.
      - `$progressPercentage` int, mirrors `course_user.progress_percentage`.
      - `$certificate`        UC13 — `?Certificate` for this student/course
                               pair (`null` until `IssueCertificateAction`
                               issues one).
--}}

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Sala de Aula</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">{{ $course->title }}</h1>
        </div>

        <x-ui.button variant="secondary" href="{{ route('student.courses.index') }}">Meus Cursos</x-ui.button>
    </div>

    <div style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--color-neutral-600); margin-bottom: 6px;">
            <span>Progresso do Curso</span>
            <span dusk="course-progress-label">{{ (int) $progressPercentage }}%</span>
        </div>
        <div style="background: var(--color-neutral-200); height: 10px; border-radius: 0px; overflow: hidden;">
            <div style="background: var(--color-accent); height: 100%; width: {{ (int) $progressPercentage }}%;" dusk="course-progress-bar"></div>
        </div>
    </div>

    <div style="margin-bottom: 24px;">
        @if($certificate)
            <x-ui.button href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate">Baixar Certificado</x-ui.button>
        @else
            <div dusk="certificate-unavailable" style="padding: 12px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); font-size: 13px; color: var(--color-neutral-600);">
                Certificado indisponível. {{ (int) $progressPercentage }}%
            </div>
        @endif
    </div>

    @forelse($modules as $module)
        <div style="margin-bottom: 20px;" dusk="module-{{ $module->id }}">
            <h2 style="font-family: var(--font-heading); font-weight: 700; font-size: 15px; margin: 0 0 10px;">{{ $module->title }}</h2>

            <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
                @forelse($module->lessons as $lesson)
                    @php
                        $isCompleted = in_array($lesson->id, $completedLessonIds ?? [], true);
                    @endphp
                    <li dusk="lesson-{{ $lesson->id }}" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border: 1px solid var(--color-divider); background: var(--color-surface);">
                        <a href="{{ route('classroom.lesson', $lesson) }}" style="color: var(--color-text); text-decoration: none; display: flex; align-items: center; gap: 10px;" dusk="open-lesson-{{ $lesson->id }}">
                            @if($isCompleted)
                                <x-ui.icon name="check" :size="16" style="color: var(--color-accent);" dusk="lesson-completed-{{ $lesson->id }}" />
                            @else
                                <x-ui.icon name="play" :size="16" style="color: var(--color-neutral-600);" />
                            @endif
                            {{ $lesson->title }}
                        </a>

                        <x-ui.badge variant="outline">{{ $lesson->type === 'quiz' ? 'Quiz' : 'Conteúdo' }}</x-ui.badge>
                    </li>
                @empty
                    <li style="padding: 16px; text-align: center; color: var(--color-neutral-600); border: 1px dashed var(--color-divider);">
                        Nenhuma lição publicada neste módulo.
                    </li>
                @endforelse
            </ul>
        </div>
    @empty
        <p style="color: var(--color-neutral-600);" dusk="no-modules">Este curso ainda não possui módulos publicados.</p>
    @endforelse
@endsection
