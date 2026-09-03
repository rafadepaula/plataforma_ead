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

    $replyAuthorRole = $reply->user->role_label;
@endphp
{{-- `forum-reply` também é gerada literalmente por
     `resources/js/modules/ForumPolling.js::appendReply()` ao injetar
     respostas via polling — mantém o nome real aqui (definido em
     `resources/scss/components/_card.scss`) para o polling continuar
     espelhando visualmente esta marcação. Qualquer mudança na estrutura
     abaixo (avatar, badge de papel, ordem dos blocos) precisa ser
     espelhada naquele módulo. --}}
<div class="forum-reply card mb-2" dusk="reply-{{ $reply->id }}" data-reply-id="{{ $reply->id }}">
    <div class="card-body py-3">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-1">
        <div class="d-flex align-items-center gap-3">
            <x-ui.avatar size="lg" :initials="$reply->user->initials" />

            <div class="small text-body-secondary">
                <strong class="text-body">{{ $reply->user->name }}</strong>
                <x-ui.badge variant="outline">{{ $replyAuthorRole }}</x-ui.badge>
                —
                <span title="{{ $reply->created_at->format('d/m/Y H:i') }}">{{ $reply->created_at->diffForHumans() }}</span>

            @include('forum.partials._edit-history-modal', [
                'modalId' => 'edit-history-reply-'.$reply->id,
                'label' => 'Resposta',
                'editedAt' => $reply->edited_at,
                'history' => $replyHistory,
            ])
            </div>
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
                {{--
                    Regra dura: toda remoção passa por confirm-modal.
                    `x-ui.confirm-modal` já é o dono do `<form>` real
                    (não pode ser tocado aqui), então o seletor dusk
                    de remoção-do-form original fica no contêiner que
                    agrupa o gatilho + o modal — o gatilho continua
                    com o seletor dusk de remoção-de-resposta intacto.
                --}}
                <div dusk="delete-reply-form-{{ $reply->id }}">
                    <x-ui.button type="button"
                                 variant="ghost"
                                 size="sm"
                                 data-bs-toggle="modal"
                                 data-bs-target="#delete-reply-modal-{{ $reply->id }}"
                                 dusk="delete-reply-{{ $reply->id }}">Apagar</x-ui.button>

                    <x-ui.confirm-modal :id="'delete-reply-modal-'.$reply->id"
                                         title="Apagar resposta"
                                         :action="route('forum-replies.destroy', [$course, $topic, $reply])"
                                         method="DELETE"
                                         variant="danger"
                                         confirm-label="Apagar"
                                         message="Esta resposta será apagada. Esta ação não poderá ser desfeita." />
                </div>
            @endif
        </div>
    </div>

    <div class="text-prewrap" dusk="reply-content-{{ $reply->id }}">{{ $reply->content }}</div>
    </div>
</div>
