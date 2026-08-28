<x-layout.public surface="white" title="Convite indisponível">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <x-ui.empty-state icon="lock" title="Convite indisponível">
                <p class="fw-semibold mb-0">
                    {{ $message ?? 'Este convite expirou ou o limite de vagas foi atingido.' }}
                </p>
                <p class="small mb-0 mt-1">
                    Peça um novo link ao responsável pelo curso.
                </p>
                <x-slot:action>
                    <x-ui.button href="/" variant="secondary" size="sm">
                        Voltar para o início
                    </x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </div>
    </div>
</x-layout.public>
