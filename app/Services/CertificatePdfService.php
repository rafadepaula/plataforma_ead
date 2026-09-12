<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * renders `resources/views/certificates/pdf.blade.php`
 * via `barryvdh/laravel-dompdf`, personalized with the issuing
 * Organization's name/CNPJ/logo (`certificate->course->organization`,
 * read `withoutGlobalScopes()` — see `certificates-architecture` — so a
 * Gestor previewing from a different active-org context, or any staff
 * download, always resolves the Course's actual owning Organization
 * rather than `null`).
 *
 * The bottom-left footer carries a QR code (SVG data URI via
 * `chillerlan/php-qrcode`, no GD needed) pointing at the public
 * `certificates.verify` route built with `OrgUrl::route()` against the
 * issuing Organization's host — the PDF may render in a request whose
 * host is not the Organization's portal (staff download from another
 * context), and the QR must resolve on the issuing portal, which is
 * host-scoped (see `certificates-architecture`).
 */
class CertificatePdfService
{
    public function __construct(
        protected CertificatePresentationBuilder $presentationBuilder,
    ) {}

    /**
     * Hands the template the measured presentation contract built by
     * `CertificatePresentationBuilder` (`logo`, `presentation`) plus the
     * QR data URI (`qrCodeDataUri`, SVG base64 — Dompdf renders data-URI
     * images reliably, unlike inline SVG) with the verification URL and
     * its short host for the human-readable caption. Paper/orientation
     * are set explicitly per document — A4 landscape — because `@page`
     * margins alone do not define orientation in Dompdf.
     */
    public function generate(Certificate $certificate): DomPdf
    {
        $certificate->loadMissing('user');

        $course = $certificate->course()->withoutGlobalScopes()->with('organization')->firstOrFail();
        $certificate->setRelation('course', $course);

        $verificationUrl = OrgUrl::route($course->organization, 'certificates.verify', $certificate->validation_hash);
        $presentation = $this->presentationBuilder->build($certificate);

        return Pdf::loadView('certificates.pdf', [
            'certificate' => $certificate,
            'verificationHost' => (string) (parse_url($verificationUrl, PHP_URL_HOST) ?? ''),
            'qrCodeDataUri' => $this->qrCodeDataUri($verificationUrl),
            'logo' => $presentation['logo'],
            'presentation' => $presentation['presentation'],
        ])->setPaper('a4', 'landscape');
    }

    /**
     * Encodes the verification URL as an SVG QR code data URI (with
     * `outputBase64` on, the v6 default, `QRMarkupSVG::dump()` already
     * returns the full `data:image/svg+xml;base64,...` string). ECC level
     * L maximizes data capacity for scan reliability at the printed 30mm
     * size; the quiet zone stays on so scanners can frame the symbol.
     * Public so `CertificatePdfTest` can assert the encoded URL.
     */
    public function qrCodeDataUri(string $verificationUrl): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => 'L',
            'addQuietzone' => true,
            'scale' => 10,
            'imageTransparent' => true,
        ]);

        return (string) (new QRCode($options))->render($verificationUrl);
    }

    /**
     * Exposes the presentation builder for tests that assert the
     * measured contract directly (`CertificatePdfTest`).
     */
    public function presentation(): CertificatePresentationBuilder
    {
        return $this->presentationBuilder;
    }
}
