{{--
    SPEC-09 §1.2 — Gestor/Admin per-course certificate list, reached via
    `GET courses/{course}/certificates` (`courses.certificates.index`,
    `App\Http\Controllers\CertificateController::index()`, Bucket B).

    Expected variables:
      - `$course`  the bound Course.
      - `$certificates`  `$course->certificates()->with('user')->latest('issued_at')->paginate(...)`.

    Each active (non-revoked) row exposes a "Revogar" button that opens this
    row's own `<x-ui.modal>` (one modal per certificate, same pattern as
    `quizzes/edit.blade.php`'s per-question edit modals) with the
    `revoke_reason` textarea. `<x-ui.confirm-modal>` is deliberately NOT used
    here: it keeps its `<form>` in the footer, so a body-level textarea would
    fall outside the submitted form, and its confirm button carries a fixed
    `confirm-modal-{id}-confirm` dusk selector instead of the
    `confirm-revoke-{id}` contract `CertificateRevocationTest` asserts on.

    Opening/closing is fully declarative (`data-bs-toggle="modal"` /
    `data-bs-dismiss="modal"`). The submit-enable toggle is wired inline below
    (see the `@push('scripts')` block) rather than via a
    `resources/js/modules/RevokeCertificateForm.js` entry — extracting it is a
    JS-layer change outside this migration's file scope.
--}}
@extends('layouts.app')

@section('content')
    <x-layout.page-header kicker="Certificados" :title="$course->title" />

    <x-ui.data-table striped :headers="['Aluno', 'Emitido em', 'Status', 'Ações']">
        @forelse($certificates as $certificate)
            <tr dusk="certificate-row-{{ $certificate->id }}">
                <td>{{ $certificate->user->name }}</td>
                <td>{{ $certificate->issued_at->format('d/m/Y') }}</td>
                <td>
                    @if($certificate->isRevoked())
                        <x-ui.badge variant="neutral" dusk="certificate-status-{{ $certificate->id }}">Revogado</x-ui.badge>
                    @else
                        <x-ui.badge variant="accent" dusk="certificate-status-{{ $certificate->id }}">Válido</x-ui.badge>
                    @endif
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ações do certificado">
                        <x-ui.button variant="secondary" size="sm" href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate-{{ $certificate->id }}">Baixar PDF</x-ui.button>

                        @unless($certificate->isRevoked())
                            <x-ui.button
                                variant="ghost"
                                size="sm"
                                data-bs-toggle="modal"
                                data-bs-target="#revoke-modal-{{ $certificate->id }}"
                                dusk="revoke-certificate-{{ $certificate->id }}"
                            >Revogar</x-ui.button>
                        @endunless
                    </div>
                </td>
            </tr>
        @empty
            <x-ui.empty-state colspan="4" message="Nenhum certificado emitido para este curso ainda." />
        @endforelse
    </x-ui.data-table>

    <x-ui.pagination :paginator="$certificates" />

    {{-- One "Revogar Certificado" modal per active certificate, mirroring
         quizzes/edit.blade.php's per-question modal pattern. --}}
    @foreach($certificates as $certificate)
        @unless($certificate->isRevoked())
            <x-ui.modal id="revoke-modal-{{ $certificate->id }}" title="Revogar Certificado" size="md">
                <form
                    method="POST"
                    action="{{ route('certificates.revoke', $certificate) }}"
                    dusk="revoke-form-{{ $certificate->id }}"
                    data-revoke-form
                >
                    @csrf
                    @method('PUT')

                    <p class="small text-body-secondary mb-3">
                        Certificado de <strong>{{ $certificate->user->name }}</strong>. Esta ação não pode ser desfeita.
                    </p>

                    <label for="revoke_reason_{{ $certificate->id }}" class="form-label fw-bold">Motivo da revogação</label>
                    <textarea
                        id="revoke_reason_{{ $certificate->id }}"
                        name="revoke_reason"
                        rows="4"
                        minlength="10"
                        maxlength="500"
                        required
                        data-revoke-reason
                        dusk="revoke-reason-{{ $certificate->id }}"
                        class="form-control @error('revoke_reason') is-invalid @enderror"
                    >{{ old('revoke_reason') }}</textarea>

                    @error('revoke_reason')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror

                    <div class="form-text" data-revoke-hint>
                        Mínimo de 10 caracteres.
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <x-ui.button variant="ghost" data-bs-dismiss="modal">Cancelar</x-ui.button>
                        <x-ui.button
                            type="submit"
                            variant="primary"
                            disabled
                            data-revoke-submit
                            dusk="confirm-revoke-{{ $certificate->id }}"
                        >Revogar Certificado</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endunless
    @endforeach

    @push('scripts')
        <script>
            // Wires every "Revogar Certificado" modal's reason textarea to
            // its submit button — server-side `RevokeCertificateRequest`
            // (min:10) is still the authority, this is UX-only.
            //
            // Opening/closing is fully declarative via Bootstrap's
            // `data-bs-toggle="modal"` / `data-bs-dismiss="modal"`, so this
            // only wires up the revoke-reason textarea/submit toggle.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-revoke-form]').forEach(function (form) {
                    var textarea = form.querySelector('[data-revoke-reason]');
                    var submit = form.querySelector('[data-revoke-submit]');
                    if (!textarea || !submit) {
                        return;
                    }

                    var toggle = function () {
                        submit.disabled = textarea.value.trim().length < 10;
                    };

                    textarea.addEventListener('input', toggle);
                    toggle();
                });
            });
        </script>
    @endpush
@endsection
