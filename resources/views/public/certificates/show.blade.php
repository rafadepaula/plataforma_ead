{{--
    SPEC-09 §2 — fully public, unauthenticated verification page for
    `GET /validar-certificado/{hash}` (`certificates.verify`, rendered by
    `App\Http\Controllers\PublicCertificateController::show()`, Bucket B).

    Deliberately NOT `@extends('layouts.app')` (requires an authenticated
    session/topbar/sidebar) nor `layouts.guest` (that layout's left panel
    is themed around "Acesse a plataforma" login copy, which doesn't fit
    a public audit page) — this is a standalone document that still pulls
    in the app's compiled CSS via `@vite` for the shared design tokens.

    Expected `$certificate` variable: the bound Certificate, with `user`,
    `course`, and `course.organization` eager-loaded. Renders for BOTH
    states (never a separate 404 view) — `revoked_at === null` ("Válido")
    and `revoked_at !== null` ("Revogado", §2's mandatory banner + reason,
    without hiding the original certificate data).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validação de Certificado — {{ $certificate->course->title }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased" style="background: var(--color-bg); color: var(--color-text); font-family: var(--font-body); margin: 0; padding: 0; min-height: 100vh;">
    <div style="max-width: 640px; margin: 0 auto; padding: 48px 24px;">
        <div style="margin-bottom: 24px; text-align: center;">
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Validação Pública de Certificado</span>
        </div>

        @if($certificate->isRevoked())
            <div dusk="certificate-revoked-banner" style="background: var(--color-danger-100, #fdecea); border: 1px solid var(--color-danger-300, #f5b5ac); color: var(--color-danger-700, #b3261e); padding: 16px 20px; margin-bottom: 24px; border-radius: 0px;">
                <strong style="display: block; font-size: 15px; margin-bottom: 4px;">
                    Certificado Revogado em {{ $certificate->revoked_at->format('d/m/Y H:i') }}
                </strong>
                <span dusk="certificate-revoke-reason" style="font-size: 13px;">
                    Motivo: {{ $certificate->revoke_reason }}
                </span>
            </div>
        @else
            <div dusk="certificate-valid-banner" style="background: var(--color-accent-100, #eafaf1); border: 1px solid var(--color-accent-300, #a6e9c4); color: var(--color-accent-700, #146c43); padding: 12px 20px; margin-bottom: 24px; text-align: center; border-radius: 0px;">
                <strong>Certificado Válido</strong>
            </div>
        @endif

        <x-ui.card>
            <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                <tr>
                    <td style="padding: 8px 0; color: var(--color-neutral-600); width: 40%;">Aluno</td>
                    <td style="padding: 8px 0; font-weight: 700;" dusk="certificate-student-name">{{ $certificate->user->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--color-neutral-600);">Curso</td>
                    <td style="padding: 8px 0; font-weight: 700;" dusk="certificate-course-title">{{ $certificate->course->title }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--color-neutral-600);">Organização Emissora</td>
                    <td style="padding: 8px 0; font-weight: 700;" dusk="certificate-org-name">{{ $certificate->course->organization->name }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--color-neutral-600);">Carga Horária</td>
                    <td style="padding: 8px 0; font-weight: 700;" dusk="certificate-workload">{{ $certificate->course->workload_hours }}h</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--color-neutral-600);">Data de Emissão</td>
                    <td style="padding: 8px 0; font-weight: 700;" dusk="certificate-issued-at">{{ $certificate->issued_at->format('d/m/Y') }}</td>
                </tr>
            </table>
        </x-ui.card>

        <p style="margin-top: 24px; font-size: 11px; color: var(--color-neutral-600); text-align: center; word-break: break-all;">
            Hash de validação: {{ $certificate->validation_hash }}
        </p>
    </div>
</body>
</html>
