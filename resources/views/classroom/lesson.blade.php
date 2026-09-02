@extends('layouts.app')

@php
    $course = $course ?? $lesson->module->course;
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[
            ['label' => 'Meus cursos', 'url' => route('student.courses.index')],
            ['label' => $course->title, 'url' => route('classroom.show', $course)],
            ['label' => $lesson->title],
        ]"
        :kicker="$course->title.' / '.$lesson->module->title"
        :title="$lesson->title"
        subtitle="Continue seus estudos e marque a lição como concluída ao terminar."
    >
        <x-slot:actions>
            <x-ui.button variant="tonal" icon="chevron-left" href="{{ route('classroom.show', $course) }}" dusk="back-to-classroom">Voltar à sala de aula</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    <div class="card ds-surface border-0 shadow-sm ds-lesson-card">
        @if($lesson->type === 'quiz')
            @include('classroom.partials._quiz-placeholder')
        @elseif(filled($lesson->video_url))
            @include('classroom.partials._video')
        @elseif(! empty($lesson->pdf_path))
            @include('classroom.partials._pdf')
        @else
            @include('classroom.partials._text-image')
        @endif
    </div>
@endsection
