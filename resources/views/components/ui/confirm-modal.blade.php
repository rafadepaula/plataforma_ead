{{--
    Modal de confirmação com form embutido (wrapper de `bootstrap.Modal`).

    Abertura e fechamento são 100% declarativos (`data-bs-toggle="modal"` /
    `data-bs-target="#{id}"` no gatilho, `data-bs-dismiss="modal"` aqui dentro):
    nenhuma linha de JS artesanal (`bootstrap-conventions` §1 e §3.2).

    O markup NUNCA emite `.show` nem `style=` — o modal nasce fechado
    (`.modal` sozinho é `display:none`) e quem adiciona `.show` é o
    `bootstrap.Modal` no momento de abrir. Ver `UiModalComponentTest`.
--}}
@props([
    'id',
    'title' => 'Confirmar ação',
    'action',
    'method' => 'DELETE',
    'confirmLabel' => 'Confirmar',
    'cancelLabel' => 'Cancelar',
    'variant' => 'danger',
    'message' => null,
])

@php
    $httpMethod = strtoupper($method);
    $spoofedMethod = $httpMethod === 'POST' ? null : $httpMethod;
    $bodyText = $message ?? 'Esta ação não poderá ser desfeita. Deseja continuar?';
@endphp

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

                <form method="POST" action="{{ $action }}" class="d-inline">
                    @csrf
                    @if ($spoofedMethod)
                        @method($spoofedMethod)
                    @endif

                    <x-ui.button type="submit"
                                 :variant="$variant"
                                 dusk="confirm-modal-{{ $id }}-confirm">{{ $confirmLabel }}</x-ui.button>
                </form>
            </div>
        </div>
    </div>
</div>
