{{--
    SPEC-15 §5/RF33 — `/admin/audit-logs` (`admin.audit-logs.index`) and
    `/gestor/audit-logs` (`gestor.audit-logs.index`), both served by the
    same `App\Http\Controllers\AuditLogController::index()` (Bucket B).

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

    The current route name (`admin.audit-logs.index` or
    `gestor.audit-logs.index`) is read at render time to resolve both the
    filter form's own `action` and the "Exportar CSV" link's `*.export`
    counterpart, so this single view serves both Admin and Gestor without
    needing two near-identical Blade files.

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
@endphp

@section('content')
    <x-slot:title>Logs de Auditoria — Plataforma EAD</x-slot:title>

    <div dusk="audit-logs-index">
        <x-layout.page-header title="Logs de Auditoria">
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
                    <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td>{{ optional($log->user)->name ?? 'Convidado/Sistema' }}</td>
                    <td>
                        <x-ui.badge variant="neutral">{{ $log->event }}</x-ui.badge>
                    </td>
                    <td>
                        @if($log->auditable_type)
                            {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $log->ip_address ?? '—' }}</td>
                    <td>
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
                <x-ui.empty-state colspan="6" message="Nenhum registro de auditoria encontrado." />
            @endforelse
        </x-ui.data-table>

        <x-ui.pagination :paginator="$auditLogs" />
    </div>

    @include('audit-logs.partials._diff-modal')
@endsection
