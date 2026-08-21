{{--
    "editar tópico" page, reached via
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
    <x-layout.page-header
        :breadcrumb="[['label' => $course->title, 'url' => route('forum.index', $course)], ['label' => $topic->title, 'url' => route('forum.show', [$course, $topic])], ['label' => 'Editar']]"
        kicker="{{ $course->title }} / Fórum"
        title="Editar Tópico"
        subtitle="Atualize o conteúdo do seu tópico." />

    <div class="max-w-640">
        <x-ui.alert variant="info" class="mb-3">
            Sua edição fica registrada no histórico público deste tópico.
        </x-ui.alert>

        <x-ui.card>
            <form method="POST" action="{{ route('forum.update', [$course, $topic]) }}" dusk="edit-topic-form">
                @csrf
                @method('PUT')

                <x-ui.input name="title" label="Título" required value="{{ old('title', $topic->title) }}" dusk="edit-topic-title" />

                <x-ui.textarea name="content" label="Conteúdo" required value="{{ old('content', $topic->content) }}" rows="12" dusk="edit-topic-content" />
                <small class="ds-caption d-block mb-3">O conteúdo é sanitizado no servidor antes de ser salvo.</small>

                <x-ui.form-actions align="end">
                    <x-ui.button variant="ghost" href="{{ route('forum.show', [$course, $topic]) }}">Cancelar</x-ui.button>
                    <x-ui.button type="submit" dusk="edit-topic-submit">Salvar Alterações</x-ui.button>
                </x-ui.form-actions>
            </form>
        </x-ui.card>
    </div>
@endsection
