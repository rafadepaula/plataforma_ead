{{--
    the Gestor/Admin's pending forum-report queue. Reached
    via `GET forum/moderation` (`forum-moderation.index`,
    `App\Http\Controllers\ForumModerationController::index()`, Bucket 2),
    restricted to `role:admin|gestor` and scoped to the Gestor's own Org
    (`ForumReport` carries no `OrgScope` — the controller must resolve
    each report's postable's `org_id` and filter manually, or join through
    the topic; see the `certificates-architecture` skill's cascade-scoping
    precedent for the same pseudo-polymorphic-without-FK shape).

    Expected variables:
      - `$reports`  a plain (non-paginated) `Collection<ForumReport>` with
                     `status = pending`, already filtered down to the
                     Gestor's own Org and each carrying an eager-resolved
                     `->postable` (a `ForumTopic|ForumReply`, resolved
                     `withTrashed()` since a Gestor may have already
                     removed the post directly — see this bucket's
                     edge-case list) and `->reporter` — see
                     `ForumModerationController::index()`, which filters
                     an already-loaded `Collection` (not a `Paginator`)
                     since the per-report `->can('view', ...)` scoping
                     can't be expressed as a single paginated SQL query.

    Expected routes (Bucket 2 contract):
      - `forum-moderation.dismiss`  POST forum/moderation/{forumReport}/dismiss
      - `forum-moderation.remove`   POST forum/moderation/{forumReport}/remove

    `postable_type` canonical values assumed by this view: the model FQCN
    (`App\Models\ForumTopic::class`/`App\Models\ForumReply::class`), not
    the short `forum_topic`/`forum_reply` strings used only at the
    HTTP/JS `data-postable-type` boundary — `ReportForumPostAction`/
    `EditForumPostAction`/`DeleteForumPostAction` all persist the FQCN,
    and `ForumReport::postable()`/`ForumPostEdit::postable()` resolve it
    via `$type::withTrashed()`, so this view keeps the same convention
    (see this feature's edge-case list on keeping the pair in sync).
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Fórum']]"
        kicker="Fórum"
        title="Acompanhamento / Moderação do fórum"
        subtitle="Revise as denúncias pendentes e decida se a publicação permanece no ar.">
        <x-slot:actions>
            <x-ui.badge variant="neutral">{{ $reports->count() }} denúncias pendentes</x-ui.badge>
        </x-slot:actions>
    </x-layout.page-header>

    <x-ui.data-table striped hover responsive :headers="['Denunciado por', 'Motivo', 'Publicação', 'Ações']">
        @forelse($reports as $report)
            @php
                $postable = $report->postable ?? null;
                $postableLabel = $report->postable_type === \App\Models\ForumTopic::class ? 'Tópico' : 'Resposta';
                $postableContent = $postable?->content;
            @endphp
            <tr dusk="report-row-{{ $report->id }}">
                <td class="fw-semibold" data-label="Denunciado por">{{ optional($report->reporter)->name ?? 'Usuário removido' }}</td>
                <td data-label="Motivo">{{ $report->reason }}</td>
                <td data-label="Publicação">
                    <x-ui.badge variant="outline">{{ $postableLabel }}</x-ui.badge>
                    <div class="small text-body-secondary text-truncate mt-1">
                        {{ $postableContent ?? 'Publicação já removida.' }}
                    </div>
                </td>
                <td data-label="Ações">
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('forum-moderation.dismiss', $report) }}" dusk="dismiss-form-{{ $report->id }}">
                            @csrf
                            <x-ui.button type="submit" variant="ghost" size="sm" dusk="dismiss-report-{{ $report->id }}">Manter</x-ui.button>
                        </form>

                        {{--
                            Regra dura: toda remoção passa por confirm-modal.
                            `x-ui.confirm-modal` já é o dono do `<form>` real
                            (não pode ser tocado aqui), então o seletor dusk
                            de remoção-do-form original fica no contêiner que
                            agrupa o gatilho + o modal — o gatilho continua
                            com o seletor dusk de remoção-de-post intacto.
                        --}}
                        <div dusk="remove-form-{{ $report->id }}">
                            <x-ui.button type="button"
                                         variant="danger"
                                         size="sm"
                                         data-bs-toggle="modal"
                                         data-bs-target="#remove-post-modal-{{ $report->id }}"
                                         dusk="remove-post-{{ $report->id }}">Remover Publicação</x-ui.button>

                            <x-ui.confirm-modal :id="'remove-post-modal-'.$report->id"
                                                 title="Remover publicação"
                                                 :action="route('forum-moderation.remove', $report)"
                                                 method="POST"
                                                 variant="danger"
                                                 confirm-label="Remover"
                                                 message="A publicação denunciada será removida e a denúncia marcada como tratada. Esta ação não poderá ser desfeita." />
                        </div>
                    </div>
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="4"
                              message="Nenhuma denúncia pendente."
                              dusk="no-pending-reports" />
        @endforelse
    </x-ui.data-table>
@endsection
