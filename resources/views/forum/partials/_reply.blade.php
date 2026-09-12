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
    $isStaff = in_array($replyAuthorRole, ['Admin', 'Gestor', 'Professor'], true);
    // `ForumReplyPolicy::report`: nada de denunciar o próprio post ou
    // posts de staff — o botão nem chega a renderizar.
    $canReportReply = auth()->check()
        && (int) auth()->id() !== (int) $reply->user_id
        && ! $isStaff;
    $roleBadgeVariant = in_array($replyAuthorRole, ['Admin', 'Gestor', 'Professor'], true)
        ? 'primary'
        : 'outline';
@endphp
{{-- `forum-reply` também é gerada literalmente por
     `resources/js/modules/ForumPolling.js::appendReply()` ao injetar
     respostas via polling — mantém o nome real aqui (definido em
     `resources/scss/components/_card.scss`) para o polling continuar
     espelhando visualmente esta marcação. Qualquer mudança na estrutura
     abaixo (avatar, badge de papel, ordem dos blocos) precisa ser
     espelhada naquele módulo. --}}
<div class="forum-reply card mb-2 {{ $isStaff ? 'forum-post-staff' : '' }} {{ $reply->is_pinned ? 'forum-post-pinned' : '' }}" dusk="reply-{{ $reply->id }}" data-reply-id="{{ $reply->id }}">
    <div class="card-body py-3">
    <div class="d-flex align-items-start justify-content-between gap-3 mb-1">
        <div class="d-flex align-items-center gap-3">
            <x-ui.avatar size="lg" :initials="$reply->user->initials" />

            <div class="small text-body-secondary d-flex align-items-center gap-2 flex-wrap">
                @if($reply->is_pinned)
                    <span class="text-body-secondary d-inline-flex align-items-center" title="Fixado" dusk="pinned-reply-badge-{{ $reply->id }}">
                    <x-ui.icon name="pin" size="14" aria-hidden="true" />
                    <span class="visually-hidden">Fixado</span>
                </span>
                @endif
                <strong class="text-body">{{ $reply->user->name }}</strong>
                <x-ui.badge :variant="$roleBadgeVariant">{{ $replyAuthorRole }}</x-ui.badge>
                <span aria-hidden="true">—</span>
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
            @can('pin', $reply)
                <form method="POST" action="{{ route('forum-replies.pin', [$course, $reply]) }}" class="d-inline">
                    @csrf
                    <x-ui.button
                        type="submit"
                        variant="ghost"
                        size="sm"
                        dusk="reply-pin-{{ $reply->id }}"
                        title="{{ $reply->is_pinned ? 'Desafixar resposta' : 'Fixar resposta no topo' }}"
                    >{{ $reply->is_pinned ? 'Desafixar' : 'Fixar' }}</x-ui.button>
                </form>
            @endcan

            @if($canReportReply)
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
            @endif

            @if($isReplyAuthor || $canModerate)
                <x-ui.button
                    variant="ghost"
                    size="sm"
                    :href="route('forum-replies.edit', [$course, $topic, $reply])"
                    dusk="edit-reply-{{ $reply->id }}"
                >Editar</x-ui.button>
            @endif

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
