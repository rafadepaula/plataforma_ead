{{--
    Estado de erro para `GET /convite/{token}` quando o token não resolve
    para um `InvitationLink` utilizável (não encontrado/expirado/revogado/
    esgotado) — `InvitationLinkInvalidException` renderizada em
    `bootstrap/app.php` (branch não-JSON) devolve esta view com status 404.

    Deliberadamente `x-layout.public` (não `layouts.guest`): esta tela não
    tem formulário nem painel institucional de "Acesse a plataforma", é uma
    página de erro pública análoga a `public/certificates/show.blade.php`.

    A mensagem chega pronta do handler em `bootstrap/app.php` (mesma string
    servida na resposta JSON), para não haver duas cópias do texto que
    `tests/Browser/MultiOrgEnrollmentTest::test_invalid_invitation_link_states_are_rejected`
    verifica com `assertSee($message)`, além de `assertMissing('@invitation-form')`.
--}}
<x-layout.public title="Convite indisponível">

    <div class="row justify-content-center">
        <div class="col-lg-6">
            <x-ui.empty-state icon="lock">
                <p class="fw-semibold mb-0">
                    {{ $message }}
                </p>
                <p class="small mb-0 mt-1">
                    Peça um novo link ao responsável pelo curso.
                </p>
            </x-ui.empty-state>
        </div>
    </div>

</x-layout.public>
