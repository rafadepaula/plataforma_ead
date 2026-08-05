<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * SPEC-09 §2 / RF16 — renders `resources/views/certificates/pdf.blade.php`
 * via `barryvdh/laravel-dompdf`, personalized with the issuing
 * Organization's name/CNPJ/logo (`certificate->course->organization`,
 * read `withoutGlobalScopes()` — see `certificates-architecture` — so a
 * Gestor previewing from a different active-org context, or any staff
 * download, always resolves the Course's actual owning Organization
 * rather than `null`).
 *
 * `$qrCodeDataUri` is always `null` for now: no QR-code composer package
 * is installed yet (adding one requires approval per this project's
 * "no dependency changes without approval" rule — see the
 * `certificates-maintenance` skill's open question). The PDF template
 * degrades gracefully to a printed verification link + hash in that case,
 * so this is not a blocking gap.
 */
class CertificatePdfService
{
    public function generate(Certificate $certificate): DomPdf
    {
        $certificate->loadMissing('user');

        $certificate->setRelation(
            'course',
            $certificate->course()->withoutGlobalScopes()->with('organization')->firstOrFail(),
        );

        $verificationUrl = route('certificates.verify', $certificate->validation_hash);

        return Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'verificationUrl' => $verificationUrl,
            'qrCodeDataUri' => null,
        ]);
    }
}
