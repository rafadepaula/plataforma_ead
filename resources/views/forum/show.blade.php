@extends('layouts.app')

@php
    /** @var \App\Models\Course $course */
    /** @var \App\Models\ForumTopic $topic */
    /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\ForumReply> $replies */
    /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\ForumPostEdit> $topicEditHistory */
    /** @var array<int, \Illuminate\Database\Eloquent\Collection<\App\Models\ForumPostEdit>> $replyEditHistories */
    /** @var bool $canEditTopic */
    /** @var bool $canDeleteTopic */
    /** @var bool $canPin */
    /** @var bool $canModerate */
    /** @var int $lastReplyId */

    $topicAuthorRole = $topic->user->role_label;

    // `$coursesCrumb` — the role-aware root crumb — is bound by
    // `ForumBreadcrumbComposer`, shared with the other forum screens.
@endphp

@section('content')
    <div class="mx-auto max-w-880">
        <x-layout.page-header
            :breadcrumb="[
                $coursesCrumb,
                ['label' => $course->title, 'url' => route('classroom.show', $course)],
                ['label' => 'Fórum', 'url' => route('forum.index', $course)],
                ['label' => $topic->title],
            ]"
            kicker="Fórum"
            :title="$topic->title"
            subtitle="Acompanhe a conversa deste tópico e responda à turma."
        >
            <x-slot:actions>
                <x-ui.button variant="secondary" href="{{ route('forum.index', $course) }}" dusk="back-to-forum">
                    Voltar ao fórum
                </x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        {{-- Original Topic Post Card --}}
        <div class="card ds-card shadow-sm mb-4" dusk="topic-post" data-topic-id="{{ $topic->id }}">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <x-ui.avatar :initials="$topic->user->initials" size="lg" />

                        <div class="small text-body-secondary">
                            @if($topic->is_pinned)
                                <x-ui.chip :static="true" variant="info" dusk="pinned-badge-{{ $topic->id }}">Fixado</x-ui.chip>
                            @endif
                            <strong class="text-body">{{ $topic->user->name }}</strong>
                            <x-ui.badge variant="outline">{{ $topicAuthorRole }}</x-ui.badge>
                            —
                            <span title="{{ $topic->created_at->format('d/m/Y H:i') }}">{{ $topic->created_at->diffForHumans() }}</span>

                            @include('forum.partials._edit-history-modal', [
                                'modalId' => 'edit-history-topic-'.$topic->id,
                                'label' => 'Tópico',
                                'editedAt' => $topic->edited_at,
                                'history' => $topicEditHistory,
                            ])
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
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
                        >
                            Denunciar
                        </x-ui.button>

                        @if($canEditTopic)
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                :href="route('forum.edit', [$course, $topic])"
                                dusk="edit-topic-{{ $topic->id }}"
                            >
                                Editar
                            </x-ui.button>
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
                            <div dusk="delete-topic-form">
                                <x-ui.button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#delete-topic-modal-{{ $topic->id }}"
                                    dusk="delete-topic"
                                >
                                    Apagar
                                </x-ui.button>

                                <x-ui.confirm-modal
                                    :id="'delete-topic-modal-'.$topic->id"
                                    title="Apagar tópico"
                                    :action="route('forum.destroy', [$course, $topic])"
                                    method="DELETE"
                                    variant="danger"
                                    confirm-label="Apagar"
                                    message="Este tópico e todas as respostas serão apagados. Esta ação não poderá ser desfeita."
                                />
                            </div>
                        @endif
                    </div>
                </div>

                <div class="text-prewrap" dusk="topic-content">{{ $topic->content }}</div>
            </div>
        </div>

        <h2 class="h6 fw-bold mb-3">Respostas</h2>

        {{-- Polling Replies Container --}}
        <div
            id="replies-list"
            data-forum-polling
            data-fetch-url="{{ route('forum-replies.fetch', [$course, $topic]) }}"
            data-last-id="{{ $lastReplyId }}"
            dusk="replies-list"
            class="d-flex flex-column gap-2 mb-4"
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

            {{-- Atributo de dado, não `dusk=` — contrato congelado não amplia.
                 `ForumPolling.js::appendReply()` remove este nó ao injetar a
                 primeira resposta em tempo real. --}}
            @if($replies->isEmpty())
                <x-ui.empty-state
                    icon="message-square"
                    title="Nenhuma resposta ainda"
                    description="Seja a primeira pessoa a responder este tópico."
                    data-forum-empty-replies
                />
            @endif
        </div>

        {{-- Reply Form --}}
        <x-ui.card class="mb-5" surface="white">
            <form method="POST" action="{{ route('forum-replies.store', [$course, $topic]) }}" dusk="new-reply-form">
                @csrf
                <x-ui.textarea
                    name="content"
                    label="Responder à discussão"
                    rows="4"
                    placeholder="Escreva sua resposta para a turma..."
                    required
                    dusk="new-reply-content"
                />

                <x-ui.form-actions align="end">
                    <x-ui.button type="submit" variant="primary" icon="message-square" dusk="new-reply-submit">
                        Responder
                    </x-ui.button>
                </x-ui.form-actions>
            </form>
        </x-ui.card>
    </div>

    {{-- Report Modal --}}
    <x-ui.modal id="report-modal" title="Denunciar publicação" size="sm">
        <form id="report-form" method="POST" action="{{ route('forum-reports.store', $course) }}" data-forum-report-form dusk="report-form">
            @csrf
            <input type="hidden" name="postable_type" data-forum-report-postable-type>
            <input type="hidden" name="postable_id" data-forum-report-postable-id>

            <x-ui.input
                type="textarea"
                name="reason"
                label="Motivo da denúncia"
                rows="4"
                placeholder="Explique o motivo da denúncia..."
                required
                dusk="report-reason"
            />
        </form>

        <x-slot:actions>
            <x-ui.button variant="secondary" data-bs-dismiss="modal">
                Cancelar
            </x-ui.button>
            <x-ui.button type="submit" form="report-form" variant="primary" dusk="report-submit">
                Enviar denúncia
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>
@endsection
