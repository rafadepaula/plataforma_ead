{{--
    SPEC-09 §1.2 — Gestor/Admin per-course certificate list, reached via
    `GET courses/{course}/certificates` (`courses.certificates.index`,
    `App\Http\Controllers\CertificateController::index()`, Bucket B).
    Mirrors `resources/views/courses/index.blade.php`'s table layout.

    Expected variables:
      - `$course`  the bound Course.
      - `$certificates`  `$course->certificates()->with('user')->latest('issued_at')->paginate(...)`.

    Each active (non-revoked) row exposes a "Revogar" button that opens
    this row's own `x-ui.modal` (one modal per certificate, same pattern
    as `quizzes/edit.blade.php`'s per-question edit modals) with the
    `revoke_reason` textarea. The modal's submit is wired inline below
    (see the `@push('scripts')` block) rather than via a new
    `resources/js/certificates.js` Vite entry, since `vite.config.js`
    only declares `resources/js/app.js` as a build input.
--}}
@extends('layouts.app')

@section('content')
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <div>
            <span style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); font-weight: 700;">Certificados</span>
            <h1 style="font-family: var(--font-heading); font-weight: 800; font-size: 24px; margin: 4px 0 0;">{{ $course->title }}</h1>
        </div>
    </div>

    <x-ui.table :headers="['Aluno', 'Emitido em', 'Status', 'Ações']">
        @forelse($certificates as $certificate)
            <tr style="border-bottom: 1px solid var(--color-divider);" dusk="certificate-row-{{ $certificate->id }}">
                <td style="padding: 12px 16px;">{{ $certificate->user->name }}</td>
                <td style="padding: 12px 16px;">{{ $certificate->issued_at->format('d/m/Y') }}</td>
                <td style="padding: 12px 16px;">
                    @if($certificate->isRevoked())
                        <x-ui.badge variant="neutral" dusk="certificate-status-{{ $certificate->id }}">Revogado</x-ui.badge>
                    @else
                        <x-ui.badge variant="accent" dusk="certificate-status-{{ $certificate->id }}">Válido</x-ui.badge>
                    @endif
                </td>
                <td style="padding: 12px 16px; display: flex; gap: 8px;">
                    <x-ui.button variant="secondary" size="sm" href="{{ route('certificates.download', $certificate) }}" dusk="download-certificate-{{ $certificate->id }}">Baixar PDF</x-ui.button>

                    @unless($certificate->isRevoked())
                        <x-ui.button
                            variant="ghost"
                            size="sm"
                            data-modal-target="revoke-modal-{{ $certificate->id }}"
                            dusk="revoke-certificate-{{ $certificate->id }}"
                        >Revogar</x-ui.button>
                    @endunless
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="padding: 24px 16px; text-align: center; color: var(--color-neutral-600);">
                    Nenhum certificado emitido para este curso ainda.
                </td>
            </tr>
        @endforelse
    </x-ui.table>

    <div style="margin-top: 20px;">
        {{ $certificates->links() }}
    </div>

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

                    <p style="font-size: 13px; color: var(--color-neutral-600); margin: 0 0 12px;">
                        Certificado de <strong>{{ $certificate->user->name }}</strong>. Esta ação não pode ser desfeita.
                    </p>

                    <label for="revoke_reason_{{ $certificate->id }}" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px;">Motivo da revogação</label>
                    <textarea
                        id="revoke_reason_{{ $certificate->id }}"
                        name="revoke_reason"
                        rows="4"
                        minlength="10"
                        maxlength="500"
                        required
                        data-revoke-reason
                        dusk="revoke-reason-{{ $certificate->id }}"
                        style="width: 100%; box-sizing: border-box; border: 1px solid var(--color-divider); padding: 10px; font-family: inherit; font-size: 13px; border-radius: 0px;"
                    >{{ old('revoke_reason') }}</textarea>

                    @error('revoke_reason')
                        <p style="color: var(--color-danger-700, #b3261e); font-size: 12px; margin: 6px 0 0;">{{ $message }}</p>
                    @enderror

                    <p data-revoke-hint style="font-size: 11px; color: var(--color-neutral-600); margin: 6px 0 0;">
                        Mínimo de 10 caracteres.
                    </p>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px;">
                        <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" data-revoke-submit disabled style="border-radius: 0px;" dusk="confirm-revoke-{{ $certificate->id }}">Revogar Certificado</button>
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
            // `x-ui.modal`'s backdrop ships with a static inline
            // `display: flex` and relies on Alpine.js's `x-show="show"`
            // to hide itself until opened — but Alpine.js is not actually
            // installed in this project (no `alpinejs` dependency/CDN
            // script anywhere), so every modal renders open by default.
            // `window.ModalManager` (registered in `app.js`) correctly
            // toggles `display` on open/close, but nothing sets the
            // initial hidden state — so this page does it explicitly for
            // its own modals rather than patching the shared component.
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.dialog-backdrop').forEach(function (backdrop) {
                    backdrop.style.display = 'none';
                });

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
