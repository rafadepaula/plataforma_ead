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
      - `forum.update`            PUT    .../topics/{topic}
      - `forum.destroy`           DELETE .../topics/{topic}
      - `forum.pin`               POST   .../topics/{topic}/pin
      - `forum-replies.store`     POST   .../topics/{topic}/replies
      - `forum-replies.fetch`     GET    .../topics/{topic}/replies/fetch?since_id=
      - `forum-reports.store`     POST   .../report  ({postable_type, postable_id, reason})
--}}
@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Fórum</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">{{ $topic->title }}</h1>
        </div>

        <x-ui.button variant="secondary" href="{{ route('forum.index', $course) }}" dusk="back-to-forum">Voltar ao Fórum</x-ui.button>
    </div>

    <div style="padding: 16px; border: 1px solid var(--color-divider); background: var(--color-surface); margin-bottom: 20px;" dusk="topic-post" data-topic-id="{{ $topic->id }}">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
            <div style="font-size: 12px; color: var(--color-neutral-600);">
                @if($topic->is_pinned)
                    <x-ui.badge variant="accent" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.badge>
                @endif
                <strong style="color: var(--color-text);">{{ $topic->user->name }}</strong>
                — {{ $topic->created_at->format('d/m/Y H:i') }}

                @include('forum.partials._edit-history-modal', [
                    'modalId' => 'edit-history-topic-'.$topic->id,
                    'label' => 'Tópico',
                    'editedAt' => $topic->edited_at,
                    'history' => $topicEditHistory,
                ])
            </div>

            <div style="display: flex; gap: 8px;">
                <button
                    type="button"
                    class="btn btn-ghost"
                    style="border-radius: 0px; padding: 4px 10px; font-size: 11px;"
                    data-forum-report-button
                    data-postable-type="forum_topic"
                    data-postable-id="{{ $topic->id }}"
                    data-modal-target="report-modal"
                    dusk="report-topic-{{ $topic->id }}"
                >Denunciar</button>

                @if($canPin)
                    <form method="POST" action="{{ route('forum.pin', [$course, $topic]) }}" dusk="pin-form-{{ $topic->id }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="border-radius: 0px; padding: 4px 10px; font-size: 11px;" dusk="pin-topic-{{ $topic->id }}">
                            {{ $topic->is_pinned ? 'Desafixar' : 'Fixar' }}
                        </button>
                    </form>
                @endif

                @if($canDeleteTopic)
                    <form method="POST" action="{{ route('forum.destroy', [$course, $topic]) }}" dusk="delete-topic-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-ghost" style="border-radius: 0px; padding: 4px 10px; font-size: 11px;" dusk="delete-topic">Apagar</button>
                    </form>
                @endif
            </div>
        </div>

        <div style="font-size: 14px; white-space: pre-wrap;" dusk="topic-content">{{ $topic->content }}</div>
    </div>

    <h2 style="font-family: var(--font-heading); font-weight: 700; font-size: 15px; margin: 0 0 12px;">Respostas</h2>

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

    <form method="POST" action="{{ route('forum-replies.store', [$course, $topic]) }}" style="margin-top: 16px;" dusk="new-reply-form">
        @csrf

        <label for="reply_content" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;">Responder</label>
        <textarea
            id="reply_content"
            name="content"
            rows="4"
            required
            dusk="new-reply-content"
            style="width: 100%; box-sizing: border-box; border: 1px solid var(--color-divider); padding: 10px; font-family: inherit; font-size: 13px; border-radius: 0px;"
        >{{ old('content') }}</textarea>
        @error('content')
            <p style="color: var(--color-danger-700, #b3261e); font-size: 12px; margin: 6px 0 0;">{{ $message }}</p>
        @enderror

        <div style="margin-top: 12px; text-align: right;">
            <button type="submit" class="btn btn-primary" style="border-radius: 0px; padding: 10px 18px;" dusk="new-reply-submit">Responder</button>
        </div>
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

            <label for="report_reason" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;">Motivo</label>
            <textarea
                id="report_reason"
                name="reason"
                rows="4"
                required
                dusk="report-reason"
                style="width: 100%; box-sizing: border-box; border: 1px solid var(--color-divider); padding: 10px; font-family: inherit; font-size: 13px; border-radius: 0px;"
            ></textarea>

            <x-slot:actions>
                <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Cancelar</button>
                <button type="submit" form="report-form" class="btn btn-primary" style="border-radius: 0px;" dusk="report-submit">Enviar Denúncia</button>
            </x-slot:actions>
        </form>
    </x-ui.modal>

    @push('scripts')
        <script>
            // Same Alpine.js-not-installed fix as `forum/index.blade.php`
            // and `certificates/index.blade.php` — hide every modal
            // backdrop on load since nothing else sets the initial state.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.dialog-backdrop').forEach(function (backdrop) {
                    backdrop.style.display = 'none';
                });
            });
        </script>
    @endpush
@endsection
