{{--
    SPEC-10 §2.2 — the Gestor/Admin's pending forum-report queue. Reached
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
    <x-layout.page-header kicker="Fórum" title="Fila de Denúncias" />

    <x-ui.data-table striped hover responsive :headers="['Denunciado por', 'Motivo', 'Publicação', 'Ações']">
        @forelse($reports as $report)
            @php
                $postable = $report->postable ?? null;
                $postableLabel = $report->postable_type === \App\Models\ForumTopic::class ? 'Tópico' : 'Resposta';
                $postableContent = $postable?->content;
            @endphp
            <tr dusk="report-row-{{ $report->id }}">
                <td>{{ optional($report->reporter)->name ?? 'Usuário removido' }}</td>
                <td>{{ $report->reason }}</td>
                <td>
                    <x-ui.badge variant="outline">{{ $postableLabel }}</x-ui.badge>
                    <div class="small text-body-secondary text-truncate max-w-400 mt-1">
                        {{ $postableContent ?? 'Publicação já removida.' }}
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('forum-moderation.dismiss', $report) }}" dusk="dismiss-form-{{ $report->id }}">
                            @csrf
                            <x-ui.button type="submit" variant="secondary" size="sm" dusk="dismiss-report-{{ $report->id }}">Descartar</x-ui.button>
                        </form>

                        <form method="POST" action="{{ route('forum-moderation.remove', $report) }}" dusk="remove-form-{{ $report->id }}">
                            @csrf
                            <x-ui.button type="submit" variant="danger" size="sm" dusk="remove-post-{{ $report->id }}">Remover Publicação</x-ui.button>
                        </form>
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
