{{--
    `/admin/audit-logs` (`admin.audit-logs.index`), served by the
    same `App\Http\Controllers\AuditLogController::index()` (Bucket B).
    Audit is Admin-only (`role:admin`) — the legacy Gestor-prefixed
    counterpart route was removed.

    Bootstrap 5.3 composition (see `bootstrap-conventions` §4/§5): the screen
    holds no raw Bootstrap markup and no `style=` — it is assembled from
    `<x-layout.page-header>`, `<x-ui.filter-bar>`, `<x-ui.data-table>`,
    `<x-ui.badge>`, `<x-ui.button>`, `<x-ui.empty-state>` and
    `<x-ui.pagination>`.

    Expected variables (Bucket B contract — see `audit-logs-conventions`):
      - `$auditLogs`        `AuditLog::query()->...->paginate(25)->withQueryString()`,
                             each row with its `user` relation eager-loaded.
      - `$organizations`    `Organization::pluck('name', 'id')` — Admin only.
      - `$eventCategories`  `[string $key => string $label]` map used to
                             populate the "Evento" dropdown (see
                             `audit-logs-conventions` for the exact keys).

    The current route name (`admin.audit-logs.index`) is read at render
    time to resolve both the filter form's own `action` and the
    "Exportar CSV" link's `*.export` counterpart, so the view keeps
    surviving a future route re-prefix without markup changes.

    The "Ver diff" trigger deliberately keeps the LEGACY `data-modal-target`
    spelling instead of `data-bs-toggle="modal"`: `AuditLogDiffModal.js` must
    fill the shared modal body BEFORE showing it, so it opens the modal
    imperatively via `bootstrap.Modal.getOrCreateInstance()` and bridges this
    attribute in its own `resolveModal()`. Adding `data-bs-toggle` would let
    Bootstrap's delegated handler open the modal independently of that render
    step. Retiring the attribute belongs to the JS phase of the migration.
--}}
@extends('layouts.app')

@php
    $auditLogsRouteName = request()->route()->getName();
    $auditLogsExportRouteName = str_replace('.index', '.export', $auditLogsRouteName);
    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@section('content')
    <x-slot:title>Logs de Auditoria — Plataforma EAD</x-slot:title>

    <div dusk="audit-logs-index">
        <x-layout.page-header kicker="Administração"
                              title="Logs de Auditoria"
                              subtitle="Acompanhe as ações realizadas na plataforma e consulte o antes/depois de cada alteração registrada."
                              :breadcrumb="[['label' => 'Administração'], ['label' => 'Logs de Auditoria']]">
            <x-slot:actions>
                <x-ui.button variant="secondary"
                             :href="route($auditLogsExportRouteName, request()->query())"
                             dusk="export-audit-logs-csv">
                    Exportar CSV
                </x-ui.button>
            </x-slot:actions>
        </x-layout.page-header>

        {{-- Filtros --}}
        <x-ui.filter-bar :action="route($auditLogsRouteName)"
                         :reset-url="route($auditLogsRouteName)"
                         label="Filtros de auditoria"
                         submit-dusk="audit-logs-filter-submit"
                         dusk="audit-logs-filter-form">
            <div class="col-md-3">
                <x-ui.input type="date"
                            name="date_from"
                            label="Data Inicial"
                            :value="request('date_from')"
                            dusk="audit-logs-date-from" />
            </div>

            <div class="col-md-3">
                <x-ui.input type="date"
                            name="date_to"
                            label="Data Final"
                            :value="request('date_to')"
                            dusk="audit-logs-date-to" />
            </div>

            @role('admin')
                <div class="col-md-3">
                    <x-ui.select name="org_id"
                                 label="Organização"
                                 :placeholder="false"
                                 dusk="audit-logs-org-filter">
                        <option value="">Todas</option>
                        @foreach($organizations ?? [] as $orgId => $orgName)
                            <option value="{{ $orgId }}" @selected((string) request('org_id') === (string) $orgId)>{{ $orgName }}</option>
                        @endforeach
                    </x-ui.select>
                </div>
            @endrole

            <div class="col-md-3">
                <x-ui.select name="event_category"
                             label="Evento"
                             :placeholder="false"
                             dusk="audit-logs-event-filter">
                    <option value="">Todos</option>
                    @foreach($eventCategories ?? [] as $key => $label)
                        <option value="{{ $key }}" @selected(request('event_category') === $key)>{{ $label }}</option>
                    @endforeach
                </x-ui.select>
            </div>

            <div class="col-md-3">
                <x-ui.input name="user_search"
                            label="Usuário"
                            :value="request('user_search')"
                            placeholder="Nome ou e-mail"
                            dusk="audit-logs-user-filter" />
            </div>
        </x-ui.filter-bar>

        <x-ui.data-table striped
                         :headers="['Data/Hora', 'Usuário', 'Evento', 'Recurso Afetado', 'IP', 'Ações']"
                         dusk="audit-logs-table">
            @forelse($auditLogs as $log)
                <tr dusk="audit-log-row-{{ $log->id }}">
                    <td data-label="Data/Hora" class="ds-tabular-nums">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td data-label="Usuário">
                        @if($log->user)
                            <div class="d-flex align-items-center gap-3">
                                <x-ui.avatar size="sm" :initials="$initialsFor($log->user->name)" aria-hidden="true" />
                                <span class="fw-semibold">{{ $log->user->name }}</span>
                            </div>
                        @else
                            Convidado/Sistema
                        @endif
                    </td>
                    <td data-label="Evento">
                        <x-ui.badge variant="neutral">{{ $log->event }}</x-ui.badge>
                    </td>
                    <td data-label="Recurso Afetado" class="ds-tabular-nums">
                        @if($log->auditable_type)
                            {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="IP" class="font-monospace small ds-tabular-nums">{{ $log->ip_address ?? '—' }}</td>
                    <td data-label="Ações">
                        @if($log->old_values || $log->new_values)
                            <x-ui.button variant="ghost"
                                         size="sm"
                                         data-modal-target="audit-diff-modal"
                                         data-audit-diff-trigger
                                         data-event="{{ $log->event }}"
                                         data-old-values="{{ json_encode($log->old_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
                                         data-new-values="{{ json_encode($log->new_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
                                         dusk="view-diff-{{ $log->id }}">
                                Ver diff
                            </x-ui.button>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-state colspan="6"
                                   icon="shield"
                                   title="Nenhum registro de auditoria encontrado."
                                   description="Ajuste os filtros acima ou aguarde novas ações serem registradas na plataforma." />
            @endforelse
        </x-ui.data-table>

        <x-ui.pagination :paginator="$auditLogs" />
    </div>

    @include('audit-logs.partials._diff-modal')
@endsection
