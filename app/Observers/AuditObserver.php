<?php

namespace App\Observers;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;

/**
 * generic "Mutação Geral" observer attached via
 * `AuditableTrait`. Builds `old_values`/`new_values` from the model's own
 * change-tracking (`getChanges()`/`getOriginal()`), redacts sensitive
 * keys, then delegates persistence to `AuditService::log()` (which
 * dual-writes DB + Monolog and never lets a failure bubble up into the
 * primary request).
 */
class AuditObserver
{
    /**
     * Attributes always redacted, regardless of whether the model
     * `$fillable`/`$hidden` declares them — `getChanges()`/`getOriginal()`
     * can surface them even when they're not visible.
     *
     * @var list<string>
     */
    private const DEFAULT_REDACTED_KEYS = ['password', 'remember_token'];

    public function created(Model $model): void
    {
        $this->record('created', $model, null, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model, $model->getOriginal(), $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, $model->getOriginal(), null);
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function record(string $action, Model $model, ?array $oldValues, ?array $newValues): void
    {
        $orgId = $model->getAttribute('org_id');
        $orgId = is_numeric($orgId) ? (int) $orgId : null;

        $userId = auth()->id();

        AuditService::log(
            event: $model->getMorphClass().'.'.$action,
            orgId: $orgId,
            userId: $userId,
            auditableType: $model->getMorphClass(),
            auditableId: $model->getKey(),
            oldValues: $this->redact($model, $oldValues),
            newValues: $this->redact($model, $newValues),
        );
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function redact(Model $model, ?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redactKeys = self::DEFAULT_REDACTED_KEYS;

        if (method_exists($model, 'getAuditRedactKeys')) {
            $redactKeys = array_merge($redactKeys, $model->getAuditRedactKeys());
        }

        foreach ($redactKeys as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[REDACTED]';
            }
        }

        return $values;
    }
}
