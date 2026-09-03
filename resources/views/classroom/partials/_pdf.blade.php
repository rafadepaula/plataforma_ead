@php
    /** Fonte única da lista: `Lesson::pdfAttachments()` (media kind=pdf, fallback da coluna legada). */
    $pdfs = $lesson->pdfAttachments();

    /** Marca d'água mínima: "Nome do Curso - Nome do Aluno", repetida uma vez por página via JS. */
    $watermark = $course->title.' - '.auth()->user()->name;
@endphp

@foreach($pdfs as $index => $pdf)
    @php
        /** Presença no disco resolvida pelo controller: nada de I/O na view. */
        $pdfExists = ($mediaAvailability ?? [])[$pdf->path] ?? true;
        /** O primeiro documento mantém o seletor sem sufixo exigido pelo contrato E2E. */
        $suffix = $index > 0 ? '-'.$index : '';
    @endphp

    @if ($pdfExists)
        <div class="ds-pdf-viewer mb-4"
             role="region"
             aria-label="Visualizador de PDF da aula"
             dusk="pdf-viewer-{{ $lesson->id }}{{ $suffix }}"
             data-pdf-viewer
             data-pdf-url="{{ route('lessons.pdf.show', [$lesson, $index]) }}"
             data-lesson-id="{{ $lesson->id }}"
             data-watermark="{{ $watermark }}"
        >
            {{--
                Toolbar 100% da plataforma, server-renderizada (os seletores
                dusk entram no snapshot por viverem aqui) e oculta até o boot —
                `PdfViewerController.js` só a revela quando o documento abre.
            --}}
            <div class="ds-pdf-toolbar d-none" data-pdf-toolbar role="toolbar" aria-label="Controles do visualizador de PDF">
                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-prev
                        dusk="pdf-prev-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Página anterior">
                    <x-ui.icon name="chevron-left" size="18" aria-hidden="true" />
                </button>

                <span class="ds-pdf-page-label" data-pdf-page dusk="pdf-page-{{ $lesson->id }}{{ $suffix }}">Página 1 de 1</span>

                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-next
                        dusk="pdf-next-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Próxima página">
                    <x-ui.icon name="chevron-right" size="18" aria-hidden="true" />
                </button>

                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-search-toggle
                        dusk="pdf-search-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Buscar no documento"
                        aria-expanded="false"
                        aria-controls="pdf-search-bar-{{ $lesson->id }}{{ $suffix }}">
                    <x-ui.icon name="search" size="18" aria-hidden="true" />
                </button>

                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-mode-toggle
                        dusk="pdf-mode-toggle-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Alternar tela cheia">
                    <x-ui.icon name="maximize" size="18" class="ds-pdf-icon-maximize" aria-hidden="true" />
                    <x-ui.icon name="minimize" size="18" class="ds-pdf-icon-minimize" aria-hidden="true" />
                </button>
            </div>

            {{--
                Busca no documento (Ctrl+F foca aqui): sem text layer no DOM
                (sem copiar-colar), com ocorrências desenhadas no canvas e
                navegação por Enter/botões. Oculta até ser aberta.
            --}}
            <div class="ds-pdf-search d-none"
                 data-pdf-search-bar
                 id="pdf-search-bar-{{ $lesson->id }}{{ $suffix }}">
                <input type="search"
                       class="form-control form-control-sm ds-pdf-search-input"
                       data-pdf-search-input
                       dusk="pdf-search-input-{{ $lesson->id }}{{ $suffix }}"
                       placeholder="Buscar no documento…"
                       aria-label="Buscar no documento"
                       autocomplete="off">
                <span class="ds-pdf-search-count"
                      data-pdf-search-count
                      dusk="pdf-search-count-{{ $lesson->id }}{{ $suffix }}"
                      aria-live="polite"></span>
                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-search-prev
                        dusk="pdf-search-prev-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Ocorrência anterior">
                    <x-ui.icon name="chevron-up" size="16" aria-hidden="true" />
                </button>
                <button type="button"
                        class="ds-pdf-button"
                        data-pdf-search-next
                        dusk="pdf-search-next-{{ $lesson->id }}{{ $suffix }}"
                        aria-label="Próxima ocorrência">
                    <x-ui.icon name="chevron-down" size="16" aria-hidden="true" />
                </button>
            </div>

            {{-- O controller renderiza uma página por `<canvas>` aqui, sem text layer (sem cópia de texto) — cada página ganha a sua mini marca d'água embaixo, sem faixa no rodapé do visualizador. --}}
            <div class="ds-pdf-stage" data-pdf-stage dusk="pdf-stage-{{ $lesson->id }}{{ $suffix }}"></div>

            {{--
                Estado degradado em RUNTIME (bytes ilegíveis, rede negada):
                o controller exibe este aviso em vez do stage vazio.
            --}}
            <div class="ds-media-unavailable d-none" data-pdf-error>
                <p class="ds-media-unavailable-title">Documento indisponível</p>
                <p class="ds-media-unavailable-text">
                    Não foi possível carregar o documento desta aula. Avise o responsável pelo curso para que ele seja reenviado.
                </p>
            </div>
        </div>

        {{-- `dusk` explícito (mesmo valor que o wrapper derivaria do `id`): o seletor entra no snapshot de contrato E2E por viver nesta tela. --}}
        <x-ui.modal id="pdf-fullscreen-{{ $lesson->id }}{{ $suffix }}" size="pdf" title="{{ $lesson->title }}" dusk="modal-pdf-fullscreen-{{ $lesson->id }}{{ $suffix }}">
            <div data-pdf-modal-slot></div>
        </x-ui.modal>
    @else
        {{-- Arquivo ausente no disco: avisa em tom neutro em vez de exibir um visualizador vazio. --}}
        <div class="ds-media-unavailable mb-4" dusk="pdf-unavailable-{{ $lesson->id }}{{ $suffix }}">
            <p class="ds-media-unavailable-title">Documento indisponível</p>
            <p class="ds-media-unavailable-text">
                O arquivo desta aula não foi encontrado. Avise o responsável pelo curso para que ele seja reenviado.
            </p>
        </div>
    @endif
@endforeach

<x-classroom.completion-bar
    :lesson="$lesson"
    :is-completed="$isCompleted ?? false"
    :tracks-progress="$tracksProgress ?? true"
    class="justify-content-end mt-4"
/>
