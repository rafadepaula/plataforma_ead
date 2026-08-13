{{--
    SPEC-10 — a single `ForumTopic`'s thread: original post + replies,
    ordered oldest-first. Reached via `GET courses/{course}/forum/topics/{topic}`
    (`forum.show`, `App\Http\Controllers\ForumTopicController::show()`,
    Bucket 2), behind the same `student.enrolled` guard as `forum.index`.

    Expected variables:
      - `$course`, `$topic`   `topic->user` eager-loaded.
      - `$replies`            Collection<ForumReply>, oldest-first, `user`
                                eager-loaded.
      - `$topicEditHistory`   Collection<ForumPostEdit> for the topic,
                                `edited_at` desc.
      - `$replyEditHistories` array<int, Collection<ForumPostEdit>> keyed
                                by reply id.
      - `$canEditTopic`, `$canDeleteTopic`, `$canPin`  bools (ForumTopicPolicy).
      - `$canModerate`        bool — Gestor/Admin of this Org (may delete
                                any reply directly, independent of `$canDeleteTopic`).
      - `$lastReplyId`        int — highest `$replies` id already rendered
                                (0 when there are none yet), the polling
                                `since_id` starting point for `ForumPolling.js`.

    Expected routes (Bucket 2 contract):
      - `forum.edit`              GET    .../topics/{topic}/edit
      - `forum.update`            PUT    .../topics/{topic}
      - `forum.destroy`           DELETE .../topics/{topic}
      - `forum.pin`               POST   .../topics/{topic}/pin
      - `forum-replies.store`     POST   .../topics/{topic}/replies
      - `forum-replies.fetch`     GET    .../topics/{topic}/replies/fetch?since_id=
      - `forum-reports.store`     POST   .../report  ({postable_type, postable_id, reason})
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Fórum" :title="$topic->title">
        <x-slot:actions>
            <x-ui.button variant="secondary" href="{{ route('forum.index', $course) }}" dusk="back-to-forum">Voltar ao Fórum</x-ui.button>
        </x-slot:actions>
    </x-layout.page-header>

    {{-- Markup `.card` cru (e não `<x-ui.card>`) pelo mesmo motivo de
         `forum/partials/_reply.blade.php`, já migrado: o post do tópico e a
         resposta são o mesmo padrão visual, e `<x-ui.card>` embrulha o slot
         num `.card-content.small` que rebaixaria o corpo do post. --}}
    <div class="card bg-body-tertiary mb-4" dusk="topic-post" data-topic-id="{{ $topic->id }}">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="small text-body-secondary">
                    @if($topic->is_pinned)
                        <x-ui.badge variant="accent" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.badge>
                    @endif
                    <strong class="text-body">{{ $topic->user->name }}</strong>
                    — {{ $topic->created_at->format('d/m/Y H:i') }}

                    @include('forum.partials._edit-history-modal', [
                        'modalId' => 'edit-history-topic-'.$topic->id,
                        'label' => 'Tópico',
                        'editedAt' => $topic->edited_at,
                        'history' => $topicEditHistory,
                    ])
                </div>

                <div class="d-flex gap-2">
                    <x-ui.button
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-forum-report-button
                        data-postable-type="forum_topic"
                        data-postable-id="{{ $topic->id }}"
                        data-bs-toggle="modal"
                        data-bs-target="#report-modal"
                        dusk="report-topic-{{ $topic->id }}"
                    >Denunciar</x-ui.button>

                    @if($canEditTopic)
                        <x-ui.button
                            variant="ghost"
                            size="sm"
                            :href="route('forum.edit', [$course, $topic])"
                            dusk="edit-topic-{{ $topic->id }}"
                        >Editar</x-ui.button>
                    @endif

                    @if($canPin)
                        <form method="POST" action="{{ route('forum.pin', [$course, $topic]) }}" dusk="pin-form-{{ $topic->id }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost" size="sm" dusk="pin-topic-{{ $topic->id }}">
                                {{ $topic->is_pinned ? 'Desafixar' : 'Fixar' }}
                            </x-ui.button>
                        </form>
                    @endif

                    @if($canDeleteTopic)
                        <form method="POST" action="{{ route('forum.destroy', [$course, $topic]) }}" dusk="delete-topic-form">
                            @csrf
                            @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm" dusk="delete-topic">Apagar</x-ui.button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="text-prewrap" dusk="topic-content">{{ $topic->content }}</div>
        </div>
    </div>

    <h2 class="h6 mb-3x">Respostas</h2>

    <div
        id="replies-list"
        data-forum-polling
        data-fetch-url="{{ route('forum-replies.fetch', [$course, $topic]) }}"
        data-last-id="{{ $lastReplyId }}"
        dusk="replies-list"
    >
        @foreach($replies as $reply)
            @include('forum.partials._reply', [
                'course' => $course,
                'topic' => $topic,
                'reply' => $reply,
                'replyEditHistories' => $replyEditHistories,
                'canModerate' => $canModerate,
            ])
        @endforeach
    </div>

    <form method="POST" action="{{ route('forum-replies.store', [$course, $topic]) }}" class="mt-4x" dusk="new-reply-form">
        @csrf

        <x-ui.textarea name="content" label="Responder" :rows="4" required dusk="new-reply-content" />

        <x-ui.form-actions align="end">
            <x-ui.button type="submit" dusk="new-reply-submit">Responder</x-ui.button>
        </x-ui.form-actions>
    </form>

    {{-- "Denunciar" reason modal, shared by every "Denunciar" button on this
         page (topic + each reply) — `ForumReportModal.js` fills in the
         `postable_type`/`postable_id` hidden fields from the button that
         opened it before submitting. --}}
    <x-ui.modal id="report-modal" title="Denunciar Publicação" size="sm">
        {{-- `id="report-form"` + the submit button's `form="report-form"`:
             see `forum/index.blade.php`'s `new-topic-form` comment — the
             `actions` slot renders outside this `<form>`'s DOM subtree. --}}
        <form id="report-form" action="{{ route('forum-reports.store', $course) }}" data-forum-report-form dusk="report-form">
            @csrf
            <input type="hidden" name="postable_type" data-forum-report-postable-type>
            <input type="hidden" name="postable_id" data-forum-report-postable-id>

            <x-ui.textarea name="reason" label="Motivo" :rows="4" required dusk="report-reason" />

            <x-slot:actions>
                <x-ui.button variant="ghost" data-bs-dismiss="modal">Cancelar</x-ui.button>
                <x-ui.button type="submit" form="report-form" dusk="report-submit">Enviar Denúncia</x-ui.button>
            </x-slot:actions>
        </form>
    </x-ui.modal>

@endsection
