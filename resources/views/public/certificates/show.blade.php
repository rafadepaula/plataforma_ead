{{--
    fully public, unauthenticated verification page for
    `GET /validar-certificado/{hash}` (`certificates.verify`, rendered by
    `App\Http\Controllers\PublicCertificateController::show()`, Bucket B).

    Deliberately NOT `@extends('layouts.app')` (requires an authenticated
    session/topbar/sidebar) nor `layouts.guest` (that layout's left panel
    is themed around "Acesse a plataforma" login copy, which doesn't fit
    a public audit page) — o shell HTML standalone vive agora em
    `<x-layout.public>`, com o `.container.py-5` padrão. A largura de
    leitura (antes `max-width: 640px`, depois `.col-lg-6`) é dada por
    `.max-w-reading` (760px).

    Expected `$certificate` variable: the bound Certificate, with `user`,
    `course`, and `course.organization` eager-loaded. Renders for BOTH
    states (never a separate 404 view) — `revoked_at === null` ("Válido")
    and `revoked_at !== null` ("Revogado", §2's mandatory banner + reason,
    without hiding the original certificate data). The download button only
    renders for authenticated visitors — the route stays staff-or-owner
    gated server-side regardless.
--}}
<x-layout.public :title="'Validação de Certificado — '.$certificate->course->title">

    <div class="max-w-reading">

        <div class="position-relative text-center mb-4">
            <span class="kicker text-primary">Validação pública</span>

            <div class="position-absolute top-0 end-0">
                <x-help-button key="certificates.verify" />
            </div>
        </div>

        <h1 class="text-center mb-4">Certificado nº {{ $certificate->validation_hash }}</h1>

        <x-ui.card class="mb-4">
            <div class="d-flex align-items-center gap-3">
                @if($certificate->isRevoked())
                    <div class="icon-circle icon-circle-critical" dusk="certificate-revoked-banner">
                        <x-ui.icon name="shield" size="28" />
                    </div>
                    <div>
                        <strong class="d-block">
                            Certificado Revogado em {{ $certificate->revoked_at->format('d/m/Y H:i') }}
                        </strong>
                        <span class="small text-body-secondary" dusk="certificate-revoke-reason">
                            Motivo: {{ $certificate->revoke_reason }}
                        </span>
                    </div>
                @else
                    <div class="icon-circle icon-circle-success" dusk="certificate-valid-banner">
                        <x-ui.icon name="check" size="28" />
                    </div>
                    <strong>Certificado Válido</strong>
                @endif
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <tbody>
                        <tr>
                            <th scope="row" class="w-25 fw-normal text-body-secondary">Aluno</th>
                            <td class="fw-bold" dusk="certificate-student-name">{{ $certificate->user->name }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal text-body-secondary">Curso</th>
                            <td class="fw-bold" dusk="certificate-course-title">{{ $certificate->course->title }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal text-body-secondary">Organização Emissora</th>
                            <td class="fw-bold" dusk="certificate-org-name">{{ $certificate->course->organization->name }}</td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal text-body-secondary">Carga Horária</th>
                            <td class="fw-bold" dusk="certificate-workload">{{ $certificate->course->workload_hours }}h</td>
                        </tr>
                        <tr>
                            <th scope="row" class="fw-normal text-body-secondary">Data de Emissão</th>
                            <td class="fw-bold" dusk="certificate-issued-at">{{ $certificate->issued_at->format('d/m/Y') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <p class="ds-caption font-monospace mt-4 mb-4 text-body-secondary text-center text-break">
            Hash de validação: {{ $certificate->validation_hash }}
        </p>

        @auth
            <div class="text-center mb-4">
                <x-ui.button variant="tonal" icon="file-text" href="{{ route('certificates.download', $certificate) }}">
                    Baixar PDF
                </x-ui.button>
            </div>
        @endauth

    </div>

</x-layout.public>
