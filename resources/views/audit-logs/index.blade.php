{{--
    SPEC-15 §5/RF33 — `/admin/audit-logs` (`admin.audit-logs.index`) and
    `/gestor/audit-logs` (`gestor.audit-logs.index`), both served by the
    same `App\Http\Controllers\AuditLogController::index()` (Bucket B).
    Mirrors `dashboard/index.blade.php`/`certificates/index.blade.php`'s
    composition: only pre-existing `<x-ui.*>` components, no new base UI
    component introduced for this screen.

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
--}}
@extends('layouts.app')

@php
    $auditLogsRouteName = request()->route()->getName();
    $auditLogsExportRouteName = str_replace('.index', '.export', $auditLogsRouteName);
@endphp

@section('content')
    <x-slot:title>Logs de Auditoria — Plataforma EAD</x-slot:title>

    <div dusk="audit-logs-index">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 0;">
                Logs de Auditoria
            </h1>

            <a href="{{ route($auditLogsExportRouteName, request()->query()) }}"
               class="btn btn-secondary"
               dusk="export-audit-logs-csv"
               style="border-radius: 0px; display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid var(--color-divider); color: var(--color-text);">
                Exportar CSV
            </a>
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route($auditLogsRouteName) }}"
              dusk="audit-logs-filter-form"
              style="display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; margin-bottom: 20px; padding: 16px; border: 1px solid var(--color-divider); background: var(--color-surface);">

            <div>
                <label for="date_from" style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Data Inicial</label>
                <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                       dusk="audit-logs-date-from"
                       style="border: 1px solid var(--color-divider); padding: 8px; font-size: 13px; border-radius: 0px;">
            </div>

            <div>
                <label for="date_to" style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Data Final</label>
                <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                       dusk="audit-logs-date-to"
                       style="border: 1px solid var(--color-divider); padding: 8px; font-size: 13px; border-radius: 0px;">
            </div>

            @role('admin')
                <div>
                    <label for="org_id" style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Organização</label>
                    <select id="org_id" name="org_id" dusk="audit-logs-org-filter"
                            style="border: 1px solid var(--color-divider); padding: 8px; font-size: 13px; border-radius: 0px; min-width: 180px;">
                        <option value="">Todas</option>
                        @foreach($organizations ?? [] as $orgId => $orgName)
                            <option value="{{ $orgId }}" @selected((string) request('org_id') === (string) $orgId)>{{ $orgName }}</option>
                        @endforeach
                    </select>
                </div>
            @endrole

            <div>
                <label for="event_category" style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Evento</label>
                <select id="event_category" name="event_category" dusk="audit-logs-event-filter"
                        style="border: 1px solid var(--color-divider); padding: 8px; font-size: 13px; border-radius: 0px; min-width: 180px;">
                    <option value="">Todos</option>
                    @foreach($eventCategories ?? [] as $key => $label)
                        <option value="{{ $key }}" @selected(request('event_category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="user_search" style="display: block; font-size: 11px; font-weight: 700; margin-bottom: 4px;">Usuário</label>
                <input type="text" id="user_search" name="user_search" value="{{ request('user_search') }}"
                       placeholder="Nome ou e-mail"
                       dusk="audit-logs-user-filter"
                       style="border: 1px solid var(--color-divider); padding: 8px; font-size: 13px; border-radius: 0px;">
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" dusk="audit-logs-filter-submit" style="border-radius: 0px; padding: 8px 16px; font-size: 13px; font-weight: 700;">
                    Filtrar
                </button>
                <a href="{{ route($auditLogsRouteName) }}" class="btn btn-ghost" style="border-radius: 0px; padding: 8px 16px; font-size: 13px; font-weight: 700; text-decoration: none;">
                    Limpar
                </a>
            </div>
        </form>

        <x-ui.table :headers="['Data/Hora', 'Usuário', 'Evento', 'Recurso Afetado', 'IP', 'Ações']" dusk="audit-logs-table">
            @forelse($auditLogs as $log)
                <tr style="border-bottom: 1px solid var(--color-divider);" dusk="audit-log-row-{{ $log->id }}">
                    <td style="padding: 12px 16px;">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                    <td style="padding: 12px 16px;">{{ optional($log->user)->name ?? 'Convidado/Sistema' }}</td>
                    <td style="padding: 12px 16px;">
                        <x-ui.badge variant="neutral">{{ $log->event }}</x-ui.badge>
                    </td>
                    <td style="padding: 12px 16px;">
                        @if($log->auditable_type)
                            {{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}
                        @else
                            —
                        @endif
                    </td>
                    <td style="padding: 12px 16px;">{{ $log->ip_address ?? '—' }}</td>
                    <td style="padding: 12px 16px;">
                        @if($log->old_values || $log->new_values)
                            <button type="button"
                                    class="btn btn-ghost"
                                    data-modal-target="audit-diff-modal"
                                    data-audit-diff-trigger
                                    data-event="{{ $log->event }}"
                                    data-old-values="{{ json_encode($log->old_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
                                    data-new-values="{{ json_encode($log->new_values ?? new \stdClass, JSON_PRETTY_PRINT) }}"
                                    dusk="view-diff-{{ $log->id }}"
                                    style="border-radius: 0px; padding: 6px 12px; font-size: 12px; font-weight: 700;">
                                Ver diff
                            </button>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                        Nenhum registro de auditoria encontrado.
                    </td>
                </tr>
            @endforelse
        </x-ui.table>

        <div style="margin-top: 20px;">
            {{ $auditLogs->links() }}
        </div>
    </div>

    @include('audit-logs.partials._diff-modal')
@endsection
