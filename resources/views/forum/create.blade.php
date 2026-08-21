{{--
    standalone "novo tópico" page, reached via
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
    <x-layout.page-header
        :breadcrumb="[['label' => $course->title, 'url' => route('forum.index', $course)], ['label' => 'Novo Tópico']]"
        kicker="{{ $course->title }} / Fórum"
        title="Novo Tópico"
        subtitle="Compartilhe sua dúvida ou observação com a turma." />

    <div class="max-w-640">
        <x-ui.card>
            <form method="POST" action="{{ route('forum.store', $course) }}" dusk="new-topic-form">
                @csrf

                <x-ui.input name="title" label="Título" required value="{{ old('title') }}" dusk="new-topic-title" />

                <x-ui.textarea name="content" label="Conteúdo" required value="{{ old('content') }}" rows="12" dusk="new-topic-content" />
                <small class="ds-caption d-block mb-3">O conteúdo é sanitizado no servidor antes de ser publicado.</small>

                <x-ui.form-actions align="end">
                    <x-ui.button variant="ghost" href="{{ route('forum.index', $course) }}">Cancelar</x-ui.button>
                    <x-ui.button type="submit" dusk="new-topic-submit">Publicar Tópico</x-ui.button>
                </x-ui.form-actions>
            </form>
        </x-ui.card>
    </div>
@endsection
