@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Meus cursos', 'url' => route('student.courses.index')], ['label' => $course->title]]"
        kicker="Sala de aula"
        :title="$course->title"
        subtitle="Acompanhe os módulos e as lições deste curso e continue de onde parou."
    >
        <x-slot:actions>
            <x-ui.button variant="tonal" icon="message-square" href="{{ route('forum.index', $course) }}">Fórum do curso</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="row g-4">
        {{-- Main Track (8 cols in lg) - STRICTLY FIRST IN DOM --}}
        <div class="col-12 col-lg-8 d-flex flex-column gap-4">
            @forelse($modules as $module)
                <x-classroom.module
                    :module="$module"
                    :index="$loop->iteration"
                    :completed-count="$module->completed_lessons_count ?? $module->lessons->whereIn('id', $completedLessonIds)->count()"
                    :total-count="$module->total_lessons_count ?? $module->lessons->count()"
                >
                    @forelse($module->lessons as $lesson)
                        <x-classroom.lesson-row
                            :lesson="$lesson"
                            :completed="in_array($lesson->id, $completedLessonIds ?? [], true)"
                        />
                    @empty
                        <li class="ds-empty p-3 text-center text-body-secondary list-unstyled">
                            Nenhuma lição publicada neste módulo.
                        </li>
                    @endforelse
                </x-classroom.module>
            @empty
                <p class="text-body-secondary" dusk="no-modules">Este curso ainda não possui módulos publicados.</p>
            @endforelse
        </div>

        {{-- Sidebar (4 cols in lg) - STRICTLY SECOND IN DOM --}}
        <div class="col-12 col-lg-4 d-flex flex-column gap-4">
            @if($nextLesson)
                <x-classroom.next-lesson-card :next-lesson="$nextLesson" :lesson="$nextLesson" />
            @endif

            <x-classroom.progress-card
                :progress-percentage="$progressPercentage"
                :completed-count="$completedCount ?? $completedLessonsCount"
                :total-count="$totalLessons ?? $totalLessonsCount"
            />

            <x-classroom.certificate-card
                :certificate="$certificate"
                :progress-percentage="$progressPercentage"
            />
        </div>
    </div>
@endsection
