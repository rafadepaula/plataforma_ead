{{--
    fully public, unauthenticated entry point of the verification
    flow: `GET /validar-certificado` (`certificates.verify` without the
    optional `{hash}`), rendered by
    `App\Http\Controllers\PublicCertificateController::show()` when no hash
    reaches it.

    This is the target of the Landing Page footer's "Validar certificado"
    link — a visitor holding a printed certificate has the hash, not a URL,
    so the page has to ask for it. The form is a plain `GET` back to the
    same endpoint, which turns the typed hash into `?hash=…` and re-enters
    `show()`, where a real hash renders `public.certificates.show` and an
    unknown one 404s exactly as a hand-typed URL would.
--}}
<x-layout.public title="Validação de Certificado">

    <div class="max-w-reading">

        <div class="position-relative text-center mb-4">
            <span class="kicker text-primary">Validação pública</span>

            <div class="position-absolute top-0 end-0">
                <x-help-button key="certificates.verify" />
            </div>

            <h1 class="mt-2">Validar certificado</h1>
            <p class="text-body-secondary">
                Informe o hash de validação impresso no certificado para conferir
                a autenticidade, o aluno, o curso e a organização emissora.
            </p>
        </div>

        <x-ui.card>
            <form method="GET" action="{{ route('certificates.verify') }}" dusk="certificate-lookup-form">
                <x-ui.input
                    name="hash"
                    label="Hash de validação"
                    :value="$hash ?? ''"
                    required
                    autofocus
                    class="font-monospace"
                    hint="O hash tem 64 caracteres e aparece no rodapé do certificado em PDF."
                    dusk="certificate-lookup-hash"
                />

                <div class="text-center">
                    <x-ui.button type="submit" icon="check" dusk="certificate-lookup-submit">
                        Validar
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>

    </div>

</x-layout.public>
