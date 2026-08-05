@extends('layouts.app')

{{--
    SPEC-07 RF20 — single-lesson player, dispatching by content shape.

    Expected `ClassroomController@showLesson` contract (Bucket 2):
      - `$lesson`         the bound Lesson (with `module.course` loaded).
      - `$isCompleted`    bool, this student's `lesson_progress.is_completed`.
      - `$watchedSeconds` int|null, this student's `lesson_progress.watched_seconds`
                           (video lessons only, used to resume polling state).

    Dispatch order matters: `type === 'quiz'` takes priority over any
    stray `youtube_url`/`pdf_path` (SPEC-07 edge case — malformed data
    should never fall through to a completable player for a quiz lesson).
--}}

@section('content')
    <div style="margin-bottom: 20px;">
        <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">
            {{ $lesson->module->course->title }} / {{ $lesson->module->title }}
        </span>
        <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">{{ $lesson->title }}</h1>
    </div>

    <div style="margin-bottom: 16px;">
        <x-ui.button variant="secondary" href="{{ route('classroom.show', $lesson->module->course) }}" dusk="back-to-classroom">Voltar à Sala de Aula</x-ui.button>
    </div>

    <x-ui.card>
        @if($lesson->type === 'quiz')
            @include('classroom.partials._quiz-placeholder')
        @elseif(! empty($lesson->youtube_url))
            @include('classroom.partials._video')
        @elseif(! empty($lesson->pdf_path))
            @include('classroom.partials._pdf')
        @else
            @include('classroom.partials._text-image')
        @endif
    </x-ui.card>
@endsection
