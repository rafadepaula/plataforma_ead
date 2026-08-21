@php
    /** @var \App\Models\Lesson $lesson */
    $type = old('type', $lesson->type ?? 'content');
@endphp

<x-ui.field-stack class="max-w-640">
    <x-ui.input
        name="title"
        label="Título"
        required
        value="{{ $lesson->title }}"
    />

    <div>
        <x-ui.select
            name="type"
            label="Tipo de Conteúdo"
            required
            :options="['content' => 'Conteúdo', 'quiz' => 'Quiz (em breve)']"
            :selected="$type"
            dusk="lesson-type-select"
        />
        <p class="form-text mt-n2 mb-0">
            Quiz será habilitado em uma etapa futura. Selecione "Conteúdo" para cadastrar Rich Text, Imagem, PDF ou vídeo do YouTube.
        </p>
    </div>

    <div id="lesson-content-fields" data-lesson-content-fields class="ds-stack">
        <x-ui.input
            type="textarea"
            name="content_text"
            label="Texto (Rich Text)"
            value="{{ $lesson->content_text }}"
        />

        <div class="ds-empty-state text-center p-4x">
            <span class="ds-empty-state-icon mb-2">
                <x-ui.icon name="upload" size="28" />
            </span>
            <label for="image" class="form-label fw-semibold d-block">Imagem</label>
            <input type="file" id="image" name="image" accept="image/*" dusk="lesson-image-input"
                   class="form-control @error('image') is-invalid @enderror" />
            @if($lesson->image_path)
                <div class="form-text">Imagem atual: {{ $lesson->image_path }}</div>
            @endif
            @error('image')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="ds-empty-state text-center p-4x">
            <span class="ds-empty-state-icon mb-2">
                <x-ui.icon name="upload" size="28" />
            </span>
            <label for="pdf" class="form-label fw-semibold d-block">PDF</label>
            <input type="file" id="pdf" name="pdf" accept="application/pdf" dusk="lesson-pdf-input"
                   class="form-control @error('pdf') is-invalid @enderror" />
            @if($lesson->pdf_path)
                <div class="form-text">PDF atual: {{ $lesson->pdf_path }}</div>
            @endif
            @error('pdf')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <x-ui.input
            name="youtube_url"
            label="URL do YouTube"
            value="{{ $lesson->youtube_url }}"
            placeholder="https://www.youtube.com/watch?v=..."
            hint="O servidor revalida o link no envio: apenas vídeos do YouTube são aceitos."
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

    <x-ui.switch
        name="is_published"
        label="Publicado"
        :checked="$lesson->is_published"
    />
</x-ui.field-stack>

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
