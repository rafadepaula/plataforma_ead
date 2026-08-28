@extends('layouts.app')

@php
    $hasPublishedLessons = $modules->contains(fn ($module) => $module->lessons->isNotEmpty());
@endphp

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
            @if($hasPublishedLessons)
                @foreach($modules as $module)
                    <x-classroom.module
                        :module="$module"
                        :index="$loop->iteration"
                        :completed-count="$module->completed_lessons_count"
                        :total-count="$module->total_lessons_count"
                    >
                        @foreach($module->lessons as $lesson)
                            <x-classroom.lesson-row :lesson="$lesson" :completed="$lesson->is_completed" />
                        @endforeach
                    </x-classroom.module>
                @endforeach
            @else
                <x-ui.empty-state
                    dusk="no-modules"
                    icon="book-open"
                    :title="$modules->isEmpty()
                        ? 'Este curso ainda não possui módulos publicados.'
                        : 'Este curso ainda não possui lições publicadas.'"
                    description="Assim que o conteúdo for publicado, ele aparece aqui."
                />
            @endif
        </div>

        {{-- Sidebar (4 cols in lg) - STRICTLY SECOND IN DOM --}}
        <div class="col-12 col-lg-4 d-flex flex-column gap-4">
            @if($nextLesson)
                <x-classroom.next-lesson-card :lesson="$nextLesson" />
            @endif

            <x-classroom.progress-card
                :progress-percentage="$progressPercentage"
                :completed-count="$completedLessonsCount"
                :total-count="$totalLessonsCount"
                :certificate-available="$certificate !== null && ! $certificate->isRevoked()"
            />

            <x-classroom.certificate-card
                :certificate="$certificate"
                :progress-percentage="$progressPercentage"
            />
        </div>
    </div>
@endsection
