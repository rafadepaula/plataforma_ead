{{--
    a single `ForumReply` row within `forum.show`'s thread.

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
<div class="forum-reply card mb-2" dusk="reply-{{ $reply->id }}" data-reply-id="{{ $reply->id }}">
    <div class="card-body py-3">
    <div class="d-flex align-items-center justify-content-between mb-1">
        <div class="small text-body-secondary">
            <strong class="text-body">{{ $reply->user->name }}</strong>
            — {{ $reply->created_at->format('d/m/Y H:i') }}

            @include('forum.partials._edit-history-modal', [
                'modalId' => 'edit-history-reply-'.$reply->id,
                'label' => 'Resposta',
                'editedAt' => $reply->edited_at,
                'history' => $replyHistory,
            ])
        </div>

        <div class="d-flex gap-2">
            <x-ui.button
                type="button"
                variant="ghost"
                size="sm"
                data-forum-report-button
                data-postable-type="forum_reply"
                data-postable-id="{{ $reply->id }}"
                data-bs-toggle="modal"
                data-bs-target="#report-modal"
                dusk="report-reply-{{ $reply->id }}"
            >Denunciar</x-ui.button>

            @if($isReplyAuthor || $canModerate)
                <form method="POST" action="{{ route('forum-replies.destroy', [$course, $topic, $reply]) }}" dusk="delete-reply-form-{{ $reply->id }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" size="sm" dusk="delete-reply-{{ $reply->id }}">Apagar</x-ui.button>
                </form>
            @endif
        </div>
    </div>

    <div class="text-prewrap" dusk="reply-content-{{ $reply->id }}">{{ $reply->content }}</div>
    </div>
</div>
