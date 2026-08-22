{{--
    Gestor/Admin per-course certificate list, reached via
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

    "Ver" opens `certificates.verify` (the public verification page) in a new
    tab, keyed off `$certificate->validation_hash`. It carries no `dusk=`
    attribute: `tests/fixtures/dusk-selectors-snapshot.json` is a closed set
    of 388 selectors (`DuskSelectorContractTest` asserts both count and
    content), so no screen in this redesign may introduce a new one.
--}}
@extends('layouts.app')

@php
    $initialsFor = function (string $name): string {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        return mb_strtoupper(collect($parts)->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp

@section('content')
    <x-layout.page-header
        :breadcrumb="[['label' => 'Cursos', 'url' => route('courses.index')], ['label' => $course->title]]"
        kicker="Certificados"
        :title="$course->title"
        subtitle="Certificados emitidos para alunos deste curso. A revogação é terminal e a linha nunca some da lista."
    />

    <x-ui.data-table striped :headers="['Aluno', 'Número', 'Emitido em', 'Status', 'Ações']">
        @forelse($certificates as $certificate)
            <tr dusk="certificate-row-{{ $certificate->id }}">
                <td data-label="Aluno">
                    <div class="d-flex align-items-center gap-3">
                        <x-ui.avatar size="sm" :initials="$initialsFor($certificate->user->name)" aria-hidden="true" />
                        <div class="min-w-0">
                            <div class="fw-semibold">{{ $certificate->user->name }}</div>
                            <div class="ds-caption text-body-secondary text-truncate">{{ $certificate->user->email }}</div>
                        </div>
                    </div>
                </td>
                <td data-label="Número"><code class="small">{{ Str::limit($certificate->validation_hash, 12, '…') }}</code></td>
                <td class="ds-tabular-nums" data-label="Emitido em">{{ $certificate->issued_at->format('d/m/Y') }}</td>
                <td data-label="Status">
                    @if($certificate->isRevoked())
                        <x-ui.badge variant="neutral" dusk="certificate-status-{{ $certificate->id }}">Revogado</x-ui.badge>
                    @else
                        <x-ui.badge variant="success" dusk="certificate-status-{{ $certificate->id }}">Válido</x-ui.badge>
                    @endif
                </td>
                <td data-label="Ações">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Ações do certificado">
                        {{--
                            "Ver" não carrega `dusk=`: os 388 seletores de
                            `tests/fixtures/dusk-selectors-snapshot.json` são um
                            conjunto fechado (`DuskSelectorContractTest` compara
                            contagem E conteúdo), então nenhuma ação nova deste
                            bucket pode introduzir um `dusk` inédito.
                        --}}
                        <x-ui.button
                            variant="tonal"
                            size="sm"
                            icon="eye"
                            href="{{ route('certificates.verify', $certificate->validation_hash) }}"
                            target="_blank"
                            rel="noopener"
                        >Ver</x-ui.button>

                        <x-ui.button variant="secondary" size="sm" icon="file-text" href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate-{{ $certificate->id }}">Baixar PDF</x-ui.button>

                        @unless($certificate->isRevoked())
                            <x-ui.button
                                variant="danger"
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
            <x-ui.empty-state
                colspan="5"
                icon="award"
                title="Nenhum certificado emitido para este curso ainda."
                description="Certificados são emitidos automaticamente quando um aluno cumpre todas as regras de conclusão ativas."
            >
                <x-slot:action>
                    <x-ui.button variant="tonal" icon="settings" href="{{ route('courses.completion-rules.index', $course) }}">
                        Ver regras de conclusão
                    </x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
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
