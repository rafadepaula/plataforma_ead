@extends('layouts.app')

{{--
    Classroom entry point for a single Course: the Module /
    Lesson tree plus a progress bar bound to `course_user.progress_percentage`.

    Expected `ClassroomController@show` contract (Bucket 2):
      - `$course`             the bound Course.
      - `$modules`            Collection<Module> eager-loaded with `lessons`
                               (published only), ordered by `order_index`.
      - `$completedLessonIds` array<int> of this student's completed
                               `lesson_progress.lesson_id` for this Course.
      - `$progressPercentage` int, mirrors `course_user.progress_percentage`.
      - `$certificate`         `?Certificate` for this student/course
                               pair (`null` until `IssueCertificateAction`
                               issues one).
--}}

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Meus Cursos', 'url' => route('student.courses.index')], ['label' => $course->title]]"
        kicker="Sala de Aula"
        :title="$course->title"
        subtitle="Acompanhe os módulos e as lições deste curso e continue de onde parou."
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('student.courses.index') }}">Meus Cursos</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    @php
        // Diretriz de mobile e responsivo: a coluna lateral (progresso +
        // certificado + próxima aula) precisa descer para o fim do conteúdo
        // abaixo de `lg`, nunca sumir — `.row` com `.col-lg-8`/`.col-lg-4`
        // (mesma convenção de `dashboard/index.blade.php`) resolve isso só
        // com ordem de DOM: a coluna principal vem primeiro no markup, a
        // lateral depois, então o empilhamento de `col-12` abaixo de `lg` já
        // deixa a lateral ao final sem precisar de `order`/CSS extra.
        $nextLesson = null;
        foreach ($modules as $module) {
            foreach ($module->lessons as $lesson) {
                if (! in_array($lesson->id, $completedLessonIds ?? [], true)) {
                    $nextLesson = $lesson;
                    break 2;
                }
            }
        }
    @endphp

    <div class="row g-4">
        <div class="col-12 col-lg-8">
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
        </div>

        <div class="col-12 col-lg-4 d-flex flex-column gap-4">
            <x-ui.card title="Progresso do curso">
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
            </x-ui.card>

            <x-ui.card title="Certificado">
                @if($certificate)
                    <x-ui.button href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate">Baixar Certificado</x-ui.button>
                @else
                    <div dusk="certificate-unavailable" class="border bg-body-tertiary py-3x px-4x small text-body-secondary">
                        Certificado indisponível. {{ (int) $progressPercentage }}%
                    </div>
                @endif
            </x-ui.card>

            @if($nextLesson)
                <x-ui.card title="Próxima aula">
                    <a href="{{ route('classroom.lesson', $nextLesson) }}" class="d-flex align-items-center gap-2x text-body text-decoration-none">
                        <x-ui.icon name="play" :size="16" class="text-body-secondary" />
                        {{ $nextLesson->title }}
                    </a>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
