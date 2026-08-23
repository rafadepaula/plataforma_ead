{{--
    Linha de lista reordenável — as 4 zonas horizontais do padrão Material
    Bootstrap: alça de arraste, título, chips de metadados (slot) e ações
    (slot). `draggable="true"` vive no `<li>` (contrato do ModuleReorder.js),
    mas a alça é a affordância visual; os botões mover para cima/baixo
    (`data-move-up`/`data-move-down`, também vinculados pelo ModuleReorder.js)
    garantem o mesmo fluxo por teclado (WCAG AA).

    `muted` pinta o título com `--text-secondary` — usado pela lição não
    publicada; nunca é o único sinal, a linha também carrega o chip
    "Não publicada".
--}}
@props([
    'id',
    'title',
    'muted' => false,
])

<li data-id="{{ $id }}"
    draggable="true"
    {{ $attributes->merge(['class' => 'ds-sortable-row list-group-item sortable-item d-flex align-items-center justify-content-between gap-3']) }}>
    <span class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
        <x-ui.icon name="grip-vertical" size="20" aria-hidden="true" class="drag-handle" />

        <span class="ds-sortable-title text-truncate fw-semibold @if($muted) text-body-secondary @endif">{{ $title }}</span>

        @isset($chips)
            <span class="d-flex align-items-center gap-2 flex-shrink-0">
                {{ $chips }}
            </span>
        @endisset
    </span>

    <span class="d-flex align-items-center gap-2 flex-shrink-0">
        <span class="d-flex align-items-center gap-1" data-sortable-moves>
            <button type="button"
                    class="btn btn-ghost btn-sm"
                    data-move-up
                    aria-label="Mover {{ $title }} para cima">
                <x-ui.icon name="chevron-up" size="16" aria-hidden="true" />
            </button>
            <button type="button"
                    class="btn btn-ghost btn-sm"
                    data-move-down
                    aria-label="Mover {{ $title }} para baixo">
                <x-ui.icon name="chevron-down" size="16" aria-hidden="true" />
            </button>
        </span>

        @isset($actions)
            {{ $actions }}
        @endisset
    </span>
</li>
