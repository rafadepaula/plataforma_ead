{{--
    SPEC-10 RF22 — standalone "novo tópico" page, reached via
    `GET courses/{course}/forum/create` (`forum.create`,
    `App\Http\Controllers\ForumTopicController::create()`). The primary UX
    for creating a topic is the inline modal on `forum.index`
    (see `forum/index.blade.php`); this page is a plain fallback with the
    same `forum.store` contract for direct navigation.

    Expected variables:
      - `$course`  the bound Course.
--}}
@extends('layouts.app')

@section('content')
    <x-ui.card title="Novo Tópico" kicker="{{ $course->title }} / Fórum">
        <form method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
            @csrf

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 640px;">
                <x-ui.input name="title" label="Título" required value="{{ old('title') }}" dusk="new-topic-title" />

                <x-ui.input type="textarea" name="content" label="Conteúdo" required value="{{ old('content') }}" dusk="new-topic-content" />
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="new-topic-submit">Publicar Tópico</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('forum.index', $course) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
