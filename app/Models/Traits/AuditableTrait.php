<?php

namespace App\Models\Traits;

use App\Observers\AuditObserver;

/**
 * SPEC-15 §4.1 — opt-in trait for models whose `created`/`updated`/
 * `deleted` mutations must be recorded to `audit_logs` as a
 * "Mutação Geral" event. Attach with `use AuditableTrait;` — no further
 * wiring required, `AuditObserver` does the work.
 *
 * A model may declare `protected array $auditRedact = [...]` to extend
 * the default `password`/`remember_token` redaction list applied by
 * `AuditObserver`.
 */
trait AuditableTrait
{
    protected static function bootAuditableTrait(): void
    {
        // `static::observe()` internally does `new static` to register the
        // observer instance — calling it directly from `boot()` would
        // re-enter `bootIfNotBooted()` before the model finishes booting
        // and trip Eloquent's re-entrancy guard. `whenBooted()` defers the
        // call until immediately after boot completes (the same pattern
        // Eloquent's own `HasEvents::bootHasEvents()` uses for the
        // `#[ObservedBy]` attribute).
        static::whenBooted(fn () => static::observe(AuditObserver::class));
    }

    /**
     * Public accessor for the model's own `protected array $auditRedact`
     * extension point — called from `AuditObserver`, which is not a
     * subclass, so it cannot read the protected property directly without
     * falling through to Eloquent's `__get()`/`getAttribute()` magic.
     *
     * @return list<string>
     */
    public function getAuditRedactKeys(): array
    {
        return property_exists($this, 'auditRedact') ? $this->auditRedact : [];
    }
}
