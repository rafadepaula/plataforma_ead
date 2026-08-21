{{--
    Botão "Remover" + modal de confirmação, em par.

    ATENÇÃO — modo de falha nº 1 da migração: o `$attributes->merge()` deste
    componente vai no BOTÃO, não no modal. É no botão que a tela pendura
    `dusk="delete-organization-{{ $org->id }}"`, `class="ms-2"`, `title=`, etc.
    Quem quiser um `dusk` no modal usa o `<x-ui.confirm-modal>` direto.

    Substitui os ~11 blocos
    `<form method="POST">@method('DELETE')<button class="btn btn-ghost">Remover</button></form>`
    que hoje excluem SEM confirmação alguma.

    O botão e o modal são IRMÃOS (o modal não é filho do botão nem de um form).
    Em tabelas, o modal fica dentro do `<td>` onde o componente foi chamado —
    markup válido, mas se algum teste de layout reclamar, o call-site deve
    chamar `<x-ui.confirm-modal>` fora da `<tr>` e apontar o botão para ele via
    a prop `id`.
--}}
@props([
    'action',
    'label' => 'Remover',
    'id' => null,
    'title' => null,
    'message' => null,
    'size' => 'sm',
    'confirmLabel' => 'Remover',
])

@php
    // Id determinístico: mesma action ⇒ mesmo id de modal, sem colisão entre linhas.
    $modalId = $id ?? 'confirm-delete-'.substr(sha1((string) $action), 0, 12);
    $modalTitle = $title ?? 'Confirmar exclusão';
@endphp

{{-- Variante `danger` já resolve para o par `--critical`/`--critical-container` com ícone. --}}
<x-ui.button variant="danger"
             icon="trash"
             :size="$size"
             data-bs-toggle="modal"
             data-bs-target="#{{ $modalId }}"
             {{ $attributes->merge() }}>{{ $label }}</x-ui.button>

<x-ui.confirm-modal :id="$modalId"
                    :title="$modalTitle"
                    :action="$action"
                    method="DELETE"
                    variant="danger"
                    :message="$message"
                    :confirm-label="$confirmLabel" />
