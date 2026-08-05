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
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Fórum</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">Fila de Denúncias</h1>
        </div>
    </div>

    <x-ui.table :headers="['Denunciado por', 'Motivo', 'Publicação', 'Ações']">
        @forelse($reports as $report)
            @php
                $postable = $report->postable ?? null;
                $postableLabel = $report->postable_type === \App\Models\ForumTopic::class ? 'Tópico' : 'Resposta';
                $postableContent = $postable?->content;
            @endphp
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="report-row-{{ $report->id }}">
                <td style="padding: 12px 16px;">{{ optional($report->reporter)->name ?? 'Usuário removido' }}</td>
                <td style="padding: 12px 16px;">{{ $report->reason }}</td>
                <td style="padding: 12px 16px; max-width: 320px;">
                    <x-ui.badge variant="outline">{{ $postableLabel }}</x-ui.badge>
                    <div style="font-size: 12px; color: var(--color-neutral-600); margin-top: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        {{ $postableContent ?? 'Publicação já removida.' }}
                    </div>
                </td>
                <td style="padding: 12px 16px; display: flex; gap: 8px;">
                    <form method="POST" action="{{ route('forum-moderation.dismiss', $report) }}" dusk="dismiss-form-{{ $report->id }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="border-radius: 0px;" dusk="dismiss-report-{{ $report->id }}">Descartar</button>
                    </form>

                    <form method="POST" action="{{ route('forum-moderation.remove', $report) }}" dusk="remove-form-{{ $report->id }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost" style="border-radius: 0px;" dusk="remove-post-{{ $report->id }}">Remover Publicação</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);" dusk="no-pending-reports">
                    Nenhuma denúncia pendente.
                </td>
            </tr>
        @endforelse
    </x-ui.table>
@endsection
