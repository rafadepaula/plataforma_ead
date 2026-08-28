{{--
    Modal de confirmação com form embutido (wrapper de `bootstrap.Modal`).

    Abertura e fechamento são 100% declarativos (`data-bs-toggle="modal"` /
    `data-bs-target="#{id}"` no gatilho, `data-bs-dismiss="modal"` aqui dentro):
    nenhuma linha de JS artesanal (`bootstrap-conventions` §1 e §3.2).

    O markup NUNCA emite `.show` nem `style=` — o modal nasce fechado
    (`.modal` sozinho é `display:none`) e quem adiciona `.show` é o
    `bootstrap.Modal` no momento de abrir. Ver `UiModalComponentTest`.

    Quando a ação a confirmar pertence a um formulário que já existe na
    tela (e que, por isso, não pode ser aninhado aqui dentro — HTML não
    permite `<form>` dentro de `<form>`), passe `form="{id-do-form}"`:
    nenhum `<form>` interno é emitido e o botão de confirmação vira
    `type="submit" form="{id}"`. O slot opcional `trigger` renderiza o
    gatilho (`data-bs-toggle="modal"`) logo antes do modal.

    É obrigatório informar `action` ou `form` — nunca nenhum dos dois:
    o componente lança `InvalidArgumentException` na renderização.

    Texto padrão calmo e explícito ("Esta ação não poderá ser desfeita...").
    Botão de confirmação da variante `danger` (default) ganha o ícone
    `trash` — cor nunca é o único sinal de ação destrutiva, a palavra do
    `$confirmLabel` continua obrigatória.
--}}
@props([
    'id',
    'title' => 'Confirmar ação',
    'action' => null,
    'form' => null,
    'method' => 'DELETE',
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'variant' => 'danger',
    'message' => null,
    'formDusk' => null,
    'confirmDusk' => null,
])

@php
    $submitsExternalForm = filled($form);

    // Exatamente uma das duas rotas de submissão é obrigatória: `action`
    // (form interno) ou `form` (form já existente na tela). Sem nenhuma
    // delas o componente emitiria um `<form action="">` apontando para a
    // própria URL da página — falha ruidosamente em vez disso.
    if (! $submitsExternalForm && blank($action)) {
        throw new \InvalidArgumentException(
            'x-ui.confirm-modal exige `action` (form interno) ou `form` (id de um form existente).',
        );
    }

    $httpMethod = strtoupper($method);
    $spoofedMethod = $httpMethod === 'POST' ? null : $httpMethod;
    $bodyText = $message ?? 'Esta ação não poderá ser desfeita. Deseja continuar?';
    $formAttributes = new \Illuminate\View\ComponentAttributeBag(
        $formDusk ? ['dusk' => $formDusk] : [],
    );
@endphp

@isset($trigger)
    {{ $trigger }}
@endisset

<div {{ $attributes->merge(['class' => 'modal fade', 'dusk' => 'confirm-modal-'.$id]) }}
     id="{{ $id }}"
     tabindex="-1"
     aria-labelledby="{{ $id }}-label"
     aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}-label">{{ $title }}</h2>
                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Fechar"
                        dusk="confirm-modal-{{ $id }}-close"></button>
            </div>

            <div class="modal-body">
                @if (trim($slot) !== '')
                    {{ $slot }}
                @else
                    <p class="mb-0">{{ $bodyText }}</p>
                @endif
            </div>

            <div class="modal-footer">
                <x-ui.button variant="ghost"
                             data-bs-dismiss="modal"
                             dusk="confirm-modal-{{ $id }}-cancel">{{ $cancelLabel }}</x-ui.button>

                @if ($submitsExternalForm)
                    <x-ui.button type="submit"
                                 form="{{ $form }}"
                                 :variant="$variant"
                                 :icon="$variant === 'danger' ? 'trash' : null"
                                 dusk="{{ $confirmDusk ?? 'confirm-modal-'.$id.'-confirm' }}">{{ $confirmLabel }}</x-ui.button>
                @else
                    <form method="POST" action="{{ $action }}" class="d-inline" {{ $formAttributes }}>
                        @csrf
                        @if ($spoofedMethod)
                            @method($spoofedMethod)
                        @endif

                        <x-ui.button type="submit"
                                     :variant="$variant"
                                     :icon="$variant === 'danger' ? 'trash' : null"
                                     dusk="{{ $confirmDusk ?? 'confirm-modal-'.$id.'-confirm' }}">{{ $confirmLabel }}</x-ui.button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
