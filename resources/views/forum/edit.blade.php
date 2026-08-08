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
    <x-ui.card title="Editar Tópico" kicker="{{ $course->title }} / Fórum">
        <form method="POST" action="{{ route('forum.update', [$course, $topic]) }}" dusk="edit-topic-form">
            @csrf
            @method('PUT')

            <div style="display: flex; flex-direction: column; gap: 20px; max-width: 640px;">
                <x-ui.input name="title" label="Título" required value="{{ old('title', $topic->title) }}" dusk="edit-topic-title" />

                <x-ui.input type="textarea" name="content" label="Conteúdo" required value="{{ old('content', $topic->content) }}" dusk="edit-topic-content" />
            </div>

            <div style="display: flex; gap: 12px; margin-top: 24px;">
                <x-ui.button type="submit" dusk="edit-topic-submit">Salvar Alterações</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('forum.show', [$course, $topic]) }}">Cancelar</x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
