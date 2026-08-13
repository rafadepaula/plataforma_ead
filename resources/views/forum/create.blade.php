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
    <x-layout.page-header kicker="{{ $course->title }} / Fórum" title="Novo Tópico" />

    <div class="row">
        <div class="col-12 col-lg-8">
            <x-ui.card>
                <form method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
                    @csrf

                    <x-ui.input name="title" label="Título" required value="{{ old('title') }}" dusk="new-topic-title" />

                    <x-ui.input type="textarea" name="content" label="Conteúdo" required value="{{ old('content') }}" rows="8" dusk="new-topic-content" />

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <x-ui.button type="submit" dusk="new-topic-submit">Publicar Tópico</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('forum.index', $course) }}">Cancelar</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
