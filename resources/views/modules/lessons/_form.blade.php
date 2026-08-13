@php
    /** @var \App\Models\Lesson $lesson */
    $type = old('type', $lesson->type ?? 'content');
@endphp

<div class="max-w-640">
    <x-ui.input
        name="title"
        label="Título"
        required
        value="{{ $lesson->title }}"
    />

    <x-ui.select
        name="type"
        label="Tipo de Conteúdo"
        required
        :options="['content' => 'Conteúdo', 'quiz' => 'Quiz (em breve)']"
        :selected="$type"
        dusk="lesson-type-select"
    />
    <p class="form-text mb-3">
        Quiz será habilitado em uma etapa futura (SPEC-08). Selecione "Conteúdo" para cadastrar Rich Text, Imagem, PDF ou vídeo do YouTube.
    </p>

    <div id="lesson-content-fields" data-lesson-content-fields>
        <x-ui.input
            type="textarea"
            name="content_text"
            label="Texto (Rich Text)"
            value="{{ $lesson->content_text }}"
        />

        <div class="mb-3">
            <label for="image" class="form-label">Imagem</label>
            <input type="file" id="image" name="image" accept="image/*" dusk="lesson-image-input"
                   class="form-control @error('image') is-invalid @enderror" />
            @if($lesson->image_path)
                <div class="form-text">Imagem atual: {{ $lesson->image_path }}</div>
            @endif
            @error('image')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="pdf" class="form-label">PDF</label>
            <input type="file" id="pdf" name="pdf" accept="application/pdf" dusk="lesson-pdf-input"
                   class="form-control @error('pdf') is-invalid @enderror" />
            @if($lesson->pdf_path)
                <div class="form-text">PDF atual: {{ $lesson->pdf_path }}</div>
            @endif
            @error('pdf')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <x-ui.input
            name="youtube_url"
            label="URL do YouTube"
            value="{{ $lesson->youtube_url }}"
            placeholder="https://www.youtube.com/watch?v=..."
            dusk="lesson-youtube-input"
        />

        <div data-youtube-preview-wrapper class="mb-3 d-none">
            <span class="form-label d-block">Pré-visualização</span>
            <div class="ratio ratio-16x9 border bg-body-secondary max-w-480">
                <iframe data-youtube-preview-frame dusk="youtube-preview" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <div class="form-check mb-3">
        <input type="checkbox" id="is_published" name="is_published" value="1"
               @checked(old('is_published', $lesson->is_published))
               class="form-check-input" />
        <label for="is_published" class="form-check-label fw-semibold">Publicado</label>
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            // Purely presentational: toggles the content-kind fields based on
            // the `type` select, and renders a live sanitized YouTube embed
            // preview client-side. The server is always the source of truth —
            // `YoutubeSanitizerService` re-validates on submit — this preview
            // is a best-effort convenience and rejects anything that doesn't
            // look like a genuine youtube.com/youtu.be link.
            const typeSelect = document.querySelector('[dusk="lesson-type-select"]');
            const contentFields = document.querySelector('[data-lesson-content-fields]');
            const youtubeInput = document.querySelector('[dusk="lesson-youtube-input"]');
            const previewWrapper = document.querySelector('[data-youtube-preview-wrapper]');
            const previewFrame = document.querySelector('[data-youtube-preview-frame]');

            const YOUTUBE_PATTERN = /^https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$/i;

            function toggleContentFields() {
                if (!typeSelect || !contentFields) return;
                contentFields.classList.toggle('d-none', typeSelect.value === 'quiz');
            }

            function updateYoutubePreview() {
                if (!youtubeInput || !previewWrapper || !previewFrame) return;
                const match = youtubeInput.value.trim().match(YOUTUBE_PATTERN);

                if (match) {
                    previewFrame.src = `https://www.youtube.com/embed/${match[1]}`;
                    previewWrapper.classList.remove('d-none');
                } else {
                    previewFrame.src = '';
                    previewWrapper.classList.add('d-none');
                }
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', toggleContentFields);
                toggleContentFields();
            }

            if (youtubeInput) {
                youtubeInput.addEventListener('input', updateYoutubePreview);
                updateYoutubePreview();
            }
        })();
    </script>
@endpush
