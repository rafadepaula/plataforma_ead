{{--
    SPEC-10 §2.1 — "editar tópico" page, reached via
    `GET courses/{course}/forum/topics/{topic}/edit` (`forum.edit`,
    `App\Http\Controllers\ForumTopicController::edit()`). Authorized the
    same way as `forum.update` (`ForumTopicPolicy::update()` — post author,
    or a same-org Gestor/Admin), no time limit. Submitting posts to the
    existing `forum.update` contract, which routes through
    `EditForumPostAction` (writes the pre-edit `forum_post_edits` snapshot,
    then sanitizes + saves the new content).

    Expected variables:
      - `$course`  the bound Course.
      - `$topic`   the bound ForumTopic being edited.
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="{{ $course->title }} / Fórum" title="Editar Tópico" />

    <div class="row">
        <div class="col-12 col-lg-8">
            <x-ui.card>
                <form method="POST" action="{{ route('forum.update', [$course, $topic]) }}" dusk="edit-topic-form">
                    @csrf
                    @method('PUT')

                    <x-ui.input name="title" label="Título" required value="{{ old('title', $topic->title) }}" dusk="edit-topic-title" />

                    <x-ui.input type="textarea" name="content" label="Conteúdo" required value="{{ old('content', $topic->content) }}" rows="8" dusk="edit-topic-content" />

                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <x-ui.button type="submit" dusk="edit-topic-submit">Salvar Alterações</x-ui.button>
                        <x-ui.button variant="secondary" href="{{ route('forum.show', [$course, $topic]) }}">Cancelar</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
@endsection
