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
    <x-layout.page-header kicker="Sala de Aula" :title="$course->title">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('student.courses.index') }}">Meus Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="mb-4">
        <div class="d-flex justify-content-between small text-body-secondary mb-2">
            <span>Progresso do Curso</span>
            <span dusk="course-progress-label">{{ (int) $progressPercentage }}%</span>
        </div>

        {{-- O seletor dusk course-progress-bar fica no wrapper `.progress` (o
             elemento visível equivalente à barra artesanal anterior); a barra
             de preenchimento interna recebe course-progress-bar-bar do
             próprio componente. --}}
        <x-ui.progress :value="(int) $progressPercentage"
                       :height="8"
                       label="Progresso do Curso"
                       dusk="course-progress-bar" />
    </div>

    <div class="mb-4">
        @if($certificate)
            <x-ui.button href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate">Baixar Certificado</x-ui.button>
        @else
            <div dusk="certificate-unavailable" class="border bg-body-tertiary py-3x px-4x small text-body-secondary">
                Certificado indisponível. {{ (int) $progressPercentage }}%
            </div>
        @endif
    </div>

    @forelse($modules as $module)
        <div class="mb-4" dusk="module-{{ $module->id }}">
            <h2 class="h6 mb-2x">{{ $module->title }}</h2>

            <ul class="list-unstyled d-flex flex-column gap-2x mb-0">
                @forelse($module->lessons as $lesson)
                    @php
                        $isCompleted = in_array($lesson->id, $completedLessonIds ?? [], true);
                    @endphp
                    <li dusk="lesson-{{ $lesson->id }}" class="d-flex align-items-center justify-content-between gap-3x border bg-body-tertiary py-3x px-4x">
                        <a href="{{ route('classroom.lesson', $lesson) }}" class="d-flex align-items-center gap-2x text-body text-decoration-none" dusk="open-lesson-{{ $lesson->id }}">
                            @if($isCompleted)
                                <x-ui.icon name="check" :size="16" class="text-primary" dusk="lesson-completed-{{ $lesson->id }}" />
                            @else
                                <x-ui.icon name="play" :size="16" class="text-body-secondary" />
                            @endif
                            {{ $lesson->title }}
                        </a>

                        <x-ui.badge variant="outline">{{ $lesson->type === 'quiz' ? 'Quiz' : 'Conteúdo' }}</x-ui.badge>
                    </li>
                @empty
                    <li class="border border-dashed text-center text-body-secondary p-4x">
                        Nenhuma lição publicada neste módulo.
                    </li>
                @endforelse
            </ul>
        </div>
    @empty
        <p class="text-body-secondary" dusk="no-modules">Este curso ainda não possui módulos publicados.</p>
    @endforelse
@endsection
