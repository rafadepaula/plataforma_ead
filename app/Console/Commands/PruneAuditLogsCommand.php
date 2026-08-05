<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * SPEC-15 — deletes `audit_logs` rows older than `config('audit.retention_days')`.
 * Runs `withoutGlobalScopes()` to bypass `AuditLog`'s `OrgScope` — pruning
 * is a global maintenance operation, not scoped to any single tenant (and
 * there's no authenticated user in a scheduled/console context for
 * `OrgScope` to resolve a tenant from anyway).
 */
class PruneAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit-logs:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove registros de audit_logs mais antigos que audit.retention_days (SPEC-15)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $retentionDays = (int) config('audit.retention_days');

        $count = AuditLog::withoutGlobalScopes()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Registros de auditoria removidos: {$count}");

        return Command::SUCCESS;
    }
}
