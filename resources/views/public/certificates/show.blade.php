{{--
    SPEC-09 §2 — fully public, unauthenticated verification page for
    `GET /validar-certificado/{hash}` (`certificates.verify`, rendered by
    `App\Http\Controllers\PublicCertificateController::show()`, Bucket B).

    Deliberately NOT `@extends('layouts.app')` (requires an authenticated
    session/topbar/sidebar) nor `layouts.guest` (that layout's left panel
    is themed around "Acesse a plataforma" login copy, which doesn't fit
    a public audit page) — o shell HTML standalone vive agora em
    `<x-layout.public>`, com o `.container.py-5` padrão. A largura de
    leitura (antes `max-width: 640px`) é dada pelo grid: `.row` centralizada
    + `.col-lg-6`.

    Expected `$certificate` variable: the bound Certificate, with `user`,
    `course`, and `course.organization` eager-loaded. Renders for BOTH
    states (never a separate 404 view) — `revoked_at === null` ("Válido")
    and `revoked_at !== null` ("Revogado", §2's mandatory banner + reason,
    without hiding the original certificate data).
--}}
<x-layout.public :title="'Validação de Certificado — '.$certificate->course->title">

    <div class="row justify-content-center">
        <div class="col-lg-6">

            <div class="position-relative text-center mb-4">
                <span class="kicker text-primary">Validação Pública de Certificado</span>

                <div class="position-absolute top-0 end-0">
                    <x-help-button key="certificates.verify" />
                </div>
            </div>

            @if($certificate->isRevoked())
                <x-ui.alert variant="danger" class="mb-4" dusk="certificate-revoked-banner">
                    <strong class="d-block mb-1">
                        Certificado Revogado em {{ $certificate->revoked_at->format('d/m/Y H:i') }}
                    </strong>
                    <span class="small" dusk="certificate-revoke-reason">
                        Motivo: {{ $certificate->revoke_reason }}
                    </span>
                </x-ui.alert>
            @else
                <x-ui.alert variant="success" class="mb-4" dusk="certificate-valid-banner">
                    <strong>Certificado Válido</strong>
                </x-ui.alert>
            @endif

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

            <p class="mt-4 mb-0 small text-body-secondary text-center text-break">
                Hash de validação: {{ $certificate->validation_hash }}
            </p>

        </div>
    </div>

</x-layout.public>
