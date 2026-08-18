@extends('layouts.app')

{{--
    single-lesson player, dispatching by content shape.

    Expected `ClassroomController@showLesson` contract (Bucket 2):
      - `$lesson`         the bound Lesson (with `module.course` loaded).
      - `$isCompleted`    bool, this student's `lesson_progress.is_completed`.
      - `$watchedSeconds` int|null, this student's `lesson_progress.watched_seconds`
                           (video lessons only, used to resume polling state).

    Dispatch order matters: `type === 'quiz'` takes priority over any
    stray `youtube_url`/`pdf_path` (edge case — malformed data
    should never fall through to a completable player for a quiz lesson).
--}}

@section('content')
    <x-layout.page-header :kicker="$lesson->module->course->title.' / '.$lesson->module->title"
                          :title="$lesson->title">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('classroom.show', $lesson->module->course) }}" dusk="back-to-classroom">Voltar à Sala de Aula</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

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
