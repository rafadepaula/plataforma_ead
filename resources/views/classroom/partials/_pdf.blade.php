@php
    $pdfs = $lesson->pdfs()->orderBy('id')->get();
    if ($pdfs->isEmpty() && ! empty($lesson->pdf_path)) {
        $pdfs = collect([(object) ['path' => $lesson->pdf_path]]);
    }
@endphp

@foreach($pdfs as $index => $pdf)
    @php
        $pdfUrl = Storage::url($pdf->path);
        $suffix = $index > 0 ? "-{$index}" : '';
    @endphp
    @if ($pdfUrl)
        <div class="ratio ratio-16x9 mb-4">
            <iframe
                src="{{ $pdfUrl }}"
                class="border rounded-3"
                dusk="pdf-viewer-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}"
            ></iframe>
        </div>
    @endif

    <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
        @if ($pdfUrl)
            <a href="{{ $pdfUrl }}" download class="btn btn-link text-decoration-none fw-bold small p-0 d-inline-flex align-items-center gap-2" dusk="pdf-download-{{ $lesson->id }}{{ $index > 0 ? '-'.$index : '' }}">
                <x-ui.icon name="download" size="16" />
                Baixar PDF
            </a>
        @endif
    </div>
@endforeach

<div class="d-flex align-items-center justify-content-end gap-3 mt-4">
    <x-ui.badge variant="accent" data-completion-badge dusk="lesson-completed-badge" @class(['d-none' => ! ($isCompleted ?? false)])>
        Concluída
    </x-ui.badge>

    <x-ui.button
        data-mark-complete-url="{{ route('lessons.complete', $lesson) }}"
        @class(['d-none' => $isCompleted ?? false])
        dusk="mark-complete-button"
    >
        Marcar como concluída
    </x-ui.button>
</div>
