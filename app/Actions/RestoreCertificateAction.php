<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * the certificate revocation undo path ("Validar" on the Gestor's
 * students directory). Authorization itself is
 * `CertificatePolicy::restore()`'s job (same org boundary as `revoke`);
 * this Action only performs the write plus the business-rule guards that
 * must hold no matter the caller — mirrors `RevokeCertificateAction`'s
 * "defense in depth" style.
 *
 * Restoration is the inverse of the logical revocation: `revoked_at`,
 * `revoked_by` and `revoke_reason` are cleared (never a row delete), so
 * the public validation hash keeps resolving and flips back to the
 * "Válido" branch. Note that `certificate.revoked` audit rows are
 * historical — restoring does not rewrite or remove them.
 */
class RestoreCertificateAction
{
    public function execute(Certificate $certificate, User $restorer): Certificate
    {
        if (! $certificate->isRevoked()) {
            throw ValidationException::withMessages([
                'certificate' => 'Este certificado não está revogado.',
            ]);
        }

        $certificate->update([
            'revoked_at' => null,
            'revoked_by' => null,
            'revoke_reason' => null,
        ]);

        try {
            AuditService::log(
                event: 'certificate.restored',
                orgId: $certificate->course?->org_id ? (int) $certificate->course->org_id : null,
                userId: $restorer->id,
                payload: [
                    'certificate_id' => $certificate->id,
                    'validation_hash' => $certificate->validation_hash,
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        return $certificate;
    }
}
