{{--
    printable certificate template rendered by
    `App\Services\CertificatePdfService::generate()` (Bucket B) through
    `barryvdh/laravel-dompdf`. dompdf only understands a restricted CSS
    subset (no CSS custom properties, no modern flexbox/grid), so this
    view is intentionally table/inline-style based rather than reusing
    the app's `resources/css/app.css` design tokens.

    Expected variables (documented in `certificates-conventions`):
      - `$certificate`  the bound Certificate, with `user`, `course`, and
        `course.organization` eager-loaded.
      - `$verificationUrl`  absolute URL to the public verification page
        (`route('certificates.verify', $certificate->validation_hash)`).
      - `$qrCodeDataUri`  nullable `data:image/png;base64,...` string. No
        QR-code composer package is installed yet (see
        `certificates-maintenance` — open question pending approval), so
        this degrades to a printed link + hash when null rather than
        omitting the verification path entirely.
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Certificado — {{ $certificate->course->title }}</title>
    <style>
        @page { margin: 0; }
        body {
            margin: 0;
            padding: 48px 56px;
            font-family: 'DejaVu Sans', sans-serif;
            color: #1a1a1a;
            border: 6px solid #1a1a1a;
        }
        .header { width: 100%; }
        .header td { vertical-align: middle; }
        .org-logo { max-height: 64px; max-width: 180px; }
        .org-name { font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .org-cnpj { font-size: 10px; color: #555555; }
        .kicker { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: #7a5cff; font-weight: bold; margin-top: 40px; }
        .title { font-size: 30px; font-weight: bold; margin: 8px 0 24px; }
        .body-text { font-size: 14px; line-height: 1.6; }
        .student-name { font-size: 22px; font-weight: bold; margin: 16px 0; }
        .course-title { font-weight: bold; }
        .meta-table { width: 100%; margin-top: 40px; font-size: 11px; }
        .meta-table td { padding-top: 12px; vertical-align: top; }
        .meta-label { color: #555555; text-transform: uppercase; font-size: 9px; letter-spacing: 1px; }
        .meta-value { font-size: 13px; font-weight: bold; }
        .footer { width: 100%; margin-top: 48px; }
        .footer td { vertical-align: bottom; }
        .qr-code { width: 90px; height: 90px; }
        .hash { font-size: 8px; color: #777777; word-break: break-all; }
        .verify-url { font-size: 10px; color: #333333; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60%;">
                @if($certificate->course->organization->logo_path ?? null)
                    <img class="org-logo" src="{{ public_path('storage/'.$certificate->course->organization->logo_path) }}" alt="{{ $certificate->course->organization->name }}">
                @endif
                <div class="org-name">{{ $certificate->course->organization->name }}</div>
                @if($certificate->course->organization->cnpj ?? null)
                    <div class="org-cnpj">CNPJ: {{ $certificate->course->organization->cnpj }}</div>
                @endif
            </td>
            <td style="width: 40%; text-align: right;" class="verify-url">
                Emitido em {{ $certificate->issued_at->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="kicker">Certificado de Conclusão</div>
    <div class="title">{{ $certificate->course->title }}</div>

    <div class="body-text">
        Certificamos que
        <div class="student-name">{{ $certificate->user->name }}</div>
        concluiu com aproveitamento o curso <span class="course-title">{{ $certificate->course->title }}</span>,
        com carga horária total de <strong>{{ $certificate->course->workload_hours }} horas</strong>,
        promovido por {{ $certificate->course->organization->name }}.
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 33%;">
                <div class="meta-label">Data de Emissão</div>
                <div class="meta-value">{{ $certificate->issued_at->format('d/m/Y') }}</div>
            </td>
            <td style="width: 33%;">
                <div class="meta-label">Carga Horária</div>
                <div class="meta-value">{{ $certificate->course->workload_hours }}h</div>
            </td>
            <td style="width: 34%;">
                <div class="meta-label">Organização Emissora</div>
                <div class="meta-value">{{ $certificate->course->organization->name }}</div>
            </td>
        </tr>
    </table>

    <table class="footer">
        <tr>
            <td style="width: 70%;">
                <div class="meta-label">Validação Pública</div>
                <div class="verify-url">{{ $verificationUrl }}</div>
                <div class="hash">Hash: {{ $certificate->validation_hash }}</div>
            </td>
            <td style="width: 30%; text-align: right;">
                @if($qrCodeDataUri ?? null)
                    <img class="qr-code" src="{{ $qrCodeDataUri }}" alt="QR Code de Validação">
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
