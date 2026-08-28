@use('App\Models\LessonMedia')

@php
    /** Lidas da relação `media` já pré-carregada pelo controller: nada de query na view. */
    $images = $lesson->media
        ->where('kind', LessonMedia::KIND_IMAGE)
        ->sortBy('id')
        ->values();

    if ($images->isEmpty() && ! empty($lesson->image_path)) {
        $images = collect([(object) ['path' => $lesson->image_path]]);
    }

    $hasText = ! empty($lesson->content_text);
@endphp

<div class="mx-auto w-100 ds-reading-column">
    @foreach($images as $index => $image)
        @php
            $imageUrl = Storage::url($image->path);
            /** Presença no disco resolvida pelo controller: nada de I/O na view. */
            $imageExists = ($mediaAvailability ?? [])[$image->path] ?? true;
            /** A primeira imagem mantém o seletor sem sufixo exigido pelo contrato E2E. */
            $suffix = $index > 0 ? '-'.$index : '';
        @endphp
        @if ($imageExists)
            <div class="ratio ratio-16x9 mb-4 overflow-hidden rounded-3">
                <img
                    src="{{ $imageUrl }}"
                    alt="{{ $lesson->title }}"
                    class="w-100 h-100 object-fit-cover"
                    loading="lazy"
                    dusk="lesson-image-{{ $lesson->id }}{{ $suffix }}"
                >
            </div>
        @else
            {{-- Arquivo ausente no disco: mesmo aviso neutro do PDF, em vez do ícone de imagem quebrada. --}}
            <div class="ds-media-unavailable mb-4" dusk="image-unavailable-{{ $lesson->id }}{{ $suffix }}">
                <p class="ds-media-unavailable-title">Imagem indisponível</p>
                <p class="ds-media-unavailable-text">
                    O arquivo desta aula não foi encontrado. Avise o responsável pelo curso para que ele seja reenviado.
                </p>
            </div>
        @endif
    @endforeach

    @if($hasText)
        <div class="lh-base mb-4 fs-6 ds-lesson-content" dusk="lesson-content-{{ $lesson->id }}">{{ $lesson->content_text }}</div>
    @endif

    @if(! $hasText && $images->isEmpty())
        {{-- Aula criada apenas com título: estado vazio calmo, sem alarme. --}}
        <p class="text-body-secondary mb-4" dusk="lesson-empty-{{ $lesson->id }}">
            Esta aula ainda não tem material publicado. Você pode marcá-la como concluída ao terminar.
        </p>
    @endif

    <x-classroom.completion-bar :lesson="$lesson" :is-completed="$isCompleted ?? false" :tracks-progress="$tracksProgress ?? true" />
</div>
