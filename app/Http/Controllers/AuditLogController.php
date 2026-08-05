<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPEC-15 §5/RF33 — read/query surface for the audit trail, served at
 * both `/admin/audit-logs` (`admin.audit-logs.index`) and
 * `/gestor/audit-logs` (`gestor.audit-logs.index`) — two distinct routes
 * pointing at the same controller methods (see `routes/web.php` and
 * `audit-logs-conventions`). `AuditLog`'s own `OrgScope` global scope
 * already restricts a Gestor's query to their own `org_id`; an Admin sees
 * everything (or one Org while impersonating) — no manual `org_id`
 * filtering is needed for that half of the isolation, only the explicit
 * `org_id` filter dropdown below.
 */
class AuditLogController extends Controller
{
    /**
     * @var array<string, list<string>>
     */
    private const EVENT_CATEGORIES = [
        'authentication' => ['login.success', 'login.failed', 'logout', 'password.reset'],
        'critical_actions' => [
            'impersonate.start', 'impersonate.stop', 'user.status_changed',
            'csv.import', 'essay.graded', 'certificate.issued', 'certificate.revoked',
            'content.deleted',
        ],
        // 'mutations' is matched by suffix (`.created`/`.updated`/`.deleted`)
        // rather than an explicit list, since it covers every
        // `AuditableTrait`-opted-in model's generic "Mutação Geral" event.
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        $auditLogs = $this->applyFilters(AuditLog::query(), $request)
            ->with('user')
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('audit-logs.index', [
            'auditLogs' => $auditLogs,
            'organizations' => $user->hasRole(RolesEnum::ADMIN->value) ? Organization::pluck('name', 'id') : null,
            'eventCategories' => [
                'authentication' => 'Autenticação',
                'mutations' => 'Mutações de Banco',
                'critical_actions' => 'Ações Críticas',
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(AuditLog::query(), $request)
            ->with('user')
            ->latest('created_at');

        $filename = sprintf('audit-logs-%s.csv', now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Data/Hora', 'Usuário', 'Evento', 'Recurso Afetado', 'IP', 'User Agent']);

            $query->chunk(500, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->created_at?->format('Y-m-d H:i:s'),
                        $row->user?->email ?? 'Convidado/Sistema',
                        $row->event,
                        $row->auditable_type ? "{$row->auditable_type} #{$row->auditable_id}" : '—',
                        $row->ip_address,
                        $row->user_agent,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Shared filter-building logic for `index()`/`export()` — extracted so
     * the export can never silently drift from what the on-screen table
     * shows. `org_id` is Admin-only and is ignored/stripped for a
     * non-Admin request even if present in the query string (mirrors
     * `ReportExportController`'s spoofed-`org_id` guard).
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        $user = $request->user();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->toString());
        }

        if ($user->hasRole(RolesEnum::ADMIN->value) && $request->filled('org_id')) {
            $query->where('org_id', (int) $request->query('org_id'));
        }

        if ($request->filled('event_category')) {
            $category = $request->string('event_category')->toString();

            if ($category === 'mutations') {
                $query->where(fn (Builder $q) => $q
                    ->where('event', 'like', '%.created')
                    ->orWhere('event', 'like', '%.updated')
                    ->orWhere('event', 'like', '%.deleted'))
                    ->whereNotIn('event', self::EVENT_CATEGORIES['critical_actions']);
            } elseif (array_key_exists($category, self::EVENT_CATEGORIES)) {
                $query->whereIn('event', self::EVENT_CATEGORIES[$category]);
            }
        }

        if ($request->filled('user_search')) {
            $term = $request->string('user_search')->toString();

            $query->whereHas('user', fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%"));
        }

        return $query;
    }
}
