<?php

namespace App\Actions;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * SPEC-09 §1.2 — the certificate revocation write path. Authorization
 * itself is `CertificatePolicy::revoke()`'s job (HTTP layer via
 * `RevokeCertificateRequest::authorize()`); this Action only performs the
 * write plus the business-rule guards that must hold no matter the
 * caller (mirrors `SubmitQuizAttemptAction`'s "defense in depth" style).
 *
 * Revocation is always logical: `revoked_at`/`revoked_by`/`revoke_reason`
 * are set, the row is never soft- or hard-deleted (see `Certificate`'s
 * class docblock) so the public validation hash keeps resolving.
 */
class RevokeCertificateAction
{
    public function execute(Certificate $certificate, User $revoker, string $reason): Certificate
    {
        if ($certificate->isRevoked()) {
            throw ValidationException::withMessages([
                'certificate' => 'Este certificado já foi revogado.',
            ]);
        }

        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages([
                'revoke_reason' => 'O motivo da revogação deve ter ao menos 10 caracteres.',
            ]);
        }

        $certificate->update([
            'revoked_at' => now(),
            'revoked_by' => $revoker->id,
            'revoke_reason' => $reason,
        ]);

        return $certificate;
    }
}
