<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | SPEC-15 — number of days `audit_logs` rows are kept before the
    | `audit-logs:prune` scheduled command (see routes/console.php) deletes
    | them. Independent of the `audit` Monolog channel's own file
    | rotation, configured in config/logging.php.
    |
    */

    'retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 365),

];
