{{--
    "editar resposta" page, reached via
    `GET courses/{course}/forum/topics/{topic}/replies/{reply}/edit`
    (`forum-replies.edit`, `App\Http\Controllers\ForumReplyController::edit()`).
    Authorized the same way as `forum-replies.update`
    (`ForumReplyPolicy::update()` — reply author, or a same-org
    Gestor/Admin), no time limit. Submitting posts to the existing
    `forum-replies.update` contract, which routes through
    `EditForumPostAction` (writes the pre-edit `forum_post_edits` snapshot,
    then sanitizes + saves the new content). Mirrors `forum/edit.blade.php`
    (topic edit) — same layout, same `dusk=` naming family.

    Expected variables:
      - `$course`  the parent Course (resolved bypassing `OrgScope`).
      - `$topic`   the parent ForumTopic.
      - `$reply`   the ForumReply being edited.
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[
            $coursesCrumb,
            ['label' => $course->title, 'url' => route('classroom.show', $course)],
            ['label' => 'Fórum', 'url' => route('forum.index', $course)],
            ['label' => $topic->title, 'url' => route('forum.show', [$course, $topic])],
            ['label' => 'Editar'],
        ]"
        kicker="Fórum"
        title="Editar resposta"
        subtitle="Atualize o conteúdo da sua resposta." />

    <div class="max-w-640">
        <x-ui.alert variant="info" class="mb-3">
            Sua edição fica registrada no histórico público desta resposta.
        </x-ui.alert>

        <x-ui.card>
            <form method="POST" action="{{ route('forum-replies.update', [$course, $topic, $reply]) }}" dusk="edit-reply-form">
                @csrf
                @method('PUT')

                <x-ui.textarea name="content" label="Resposta" required value="{{ old('content', $reply->content) }}" rows="12" dusk="edit-reply-content" />
                <small class="ds-caption d-block mb-3">O conteúdo é sanitizado no servidor antes de ser salvo.</small>

                <x-ui.form-actions align="end">
                    <x-ui.button variant="ghost" href="{{ route('forum.show', [$course, $topic]) }}">Cancelar</x-ui.button>
                    <x-ui.button type="submit" dusk="edit-reply-submit">Salvar alterações</x-ui.button>
                </x-ui.form-actions>
            </form>
        </x-ui.card>
    </div>
@endsection
