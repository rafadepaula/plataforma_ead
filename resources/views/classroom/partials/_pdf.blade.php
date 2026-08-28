@use('App\Models\LessonMedia')

@php
    /** Lidos da relação `media` já pré-carregada pelo controller: nada de query na view. */
    $pdfs = $lesson->media
        ->where('kind', LessonMedia::KIND_PDF)
        ->sortBy('id')
        ->values();

    if ($pdfs->isEmpty() && ! empty($lesson->pdf_path)) {
        $pdfs = collect([(object) ['path' => $lesson->pdf_path]]);
    }
@endphp

@foreach($pdfs as $index => $pdf)
    @php
        $pdfUrl = Storage::url($pdf->path);
        /** Presença no disco resolvida pelo controller: nada de I/O na view. */
        $pdfExists = ($mediaAvailability ?? [])[$pdf->path] ?? true;
        /** O primeiro documento mantém o seletor sem sufixo exigido pelo contrato E2E. */
        $suffix = $index > 0 ? '-'.$index : '';
    @endphp

    @if ($pdfExists)
        <div class="ds-ratio ds-ratio-16x9 mb-4">
            <iframe
                src="{{ $pdfUrl }}"
                class="ds-pdf-frame"
                dusk="pdf-viewer-{{ $lesson->id }}{{ $suffix }}"
            ></iframe>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
            <a href="{{ $pdfUrl }}" download class="btn btn-link text-decoration-none fw-bold small p-0 d-inline-flex align-items-center gap-2" dusk="pdf-download-{{ $lesson->id }}{{ $suffix }}">
                <x-ui.icon name="download" size="16" />
                Baixar PDF
            </a>
        </div>
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
