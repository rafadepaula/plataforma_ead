{{--
    SPEC-10 — a single `ForumReply` row within `forum.show`'s thread.

    Expected variables (passed by the parent `@foreach`):
      - `$course`, `$topic`
      - `$reply`                the bound `ForumReply`, with `user` loaded.
      - `$replyEditHistories`   array<int, Collection<ForumPostEdit>> keyed
                                 by reply id (from the parent view).
      - `$canModerate`          bool — Gestor/Admin of this Org (may delete
                                 any reply directly).

    Expected routes (Bucket 2 contract):
      - `forum-replies.destroy`  DELETE .../replies/{reply}
      - `forum-reports.store`    POST   .../report  (postable_type=forum_reply, postable_id)
--}}
@php
    $isReplyAuthor = auth()->id() === $reply->user_id;
    $replyHistory = $replyEditHistories[$reply->id] ?? collect();
@endphp
<div style="padding: 14px 16px; border: 1px solid var(--color-divider); background: var(--color-surface); margin-bottom: 10px;" dusk="reply-{{ $reply->id }}" data-reply-id="{{ $reply->id }}">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
        <div style="font-size: 12px; color: var(--color-neutral-600);">
            <strong style="color: var(--color-text);">{{ $reply->user->name }}</strong>
            — {{ $reply->created_at->format('d/m/Y H:i') }}

            @include('forum.partials._edit-history-modal', [
                'modalId' => 'edit-history-reply-'.$reply->id,
                'label' => 'Resposta',
                'editedAt' => $reply->edited_at,
                'history' => $replyHistory,
            ])
        </div>

        <div style="display: flex; gap: 8px;">
            <button
                type="button"
                class="btn btn-ghost"
                style="border-radius: 0px; padding: 4px 10px; font-size: 11px;"
                data-forum-report-button
                data-postable-type="forum_reply"
                data-postable-id="{{ $reply->id }}"
                data-modal-target="report-modal"
                dusk="report-reply-{{ $reply->id }}"
            >Denunciar</button>

            @if($isReplyAuthor || $canModerate)
                <form method="POST" action="{{ route('forum-replies.destroy', [$course, $topic, $reply]) }}" dusk="delete-reply-form-{{ $reply->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost" style="border-radius: 0px; padding: 4px 10px; font-size: 11px;" dusk="delete-reply-{{ $reply->id }}">Apagar</button>
                </form>
            @endif
        </div>
    </div>

    <div style="font-size: 14px; white-space: pre-wrap;" dusk="reply-content-{{ $reply->id }}">{{ $reply->content }}</div>
</div>
