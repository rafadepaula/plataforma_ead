@php
    /** @var \App\Models\Lesson $lesson */
    $type = old('type', $lesson->type ?? 'content');
@endphp

<div style="display: flex; flex-direction: column; gap: 20px; max-width: 640px;">
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
    <p style="font-size: 12px; color: var(--color-neutral-600); margin-top: -12px;">
        Quiz será habilitado em uma etapa futura (SPEC-08). Selecione "Conteúdo" para cadastrar Rich Text, Imagem, PDF ou vídeo do YouTube.
    </p>

    <div id="lesson-content-fields" data-lesson-content-fields style="display: flex; flex-direction: column; gap: 20px;">
        <x-ui.input
            type="textarea"
            name="content_text"
            label="Texto (Rich Text)"
            value="{{ $lesson->content_text }}"
        />

        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label for="image" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Imagem</label>
            <input type="file" id="image" name="image" accept="image/*" dusk="lesson-image-input"
                   style="border-radius: 0px; border: 1px solid var(--color-divider); padding: 8px 12px; background: var(--color-surface); color: var(--color-text);" />
            @if($lesson->image_path)
                <span style="font-size: 12px; color: var(--color-neutral-600);">Imagem atual: {{ $lesson->image_path }}</span>
            @endif
            @error('image')
                <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $message }}</span>
            @enderror
        </div>

        <div class="field" style="display: flex; flex-direction: column; gap: 6px;">
            <label for="pdf" style="font-size: 13px; font-weight: 600; color: var(--color-text);">PDF</label>
            <input type="file" id="pdf" name="pdf" accept="application/pdf" dusk="lesson-pdf-input"
                   style="border-radius: 0px; border: 1px solid var(--color-divider); padding: 8px 12px; background: var(--color-surface); color: var(--color-text);" />
            @if($lesson->pdf_path)
                <span style="font-size: 12px; color: var(--color-neutral-600);">PDF atual: {{ $lesson->pdf_path }}</span>
            @endif
            @error('pdf')
                <span style="font-size: 12px; color: var(--color-accent); font-weight: 600;">{{ $message }}</span>
            @enderror
        </div>

        <x-ui.input
            name="youtube_url"
            label="URL do YouTube"
            value="{{ $lesson->youtube_url }}"
            placeholder="https://www.youtube.com/watch?v=..."
            dusk="lesson-youtube-input"
        />

        <div data-youtube-preview-wrapper style="display: none; flex-direction: column; gap: 6px;">
            <span style="font-size: 12px; font-weight: 600; color: var(--color-text);">Pré-visualização</span>
            <div style="aspect-ratio: 16 / 9; max-width: 480px; border: 1px solid var(--color-divider); background: var(--color-neutral-200);">
                <iframe data-youtube-preview-frame dusk="youtube-preview" width="100%" height="100%" frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
            </div>
        </div>
    </div>

    <div class="field" style="display: flex; align-items: center; gap: 8px;">
        <input type="checkbox" id="is_published" name="is_published" value="1"
               @checked(old('is_published', $lesson->is_published))
               style="width: 16px; height: 16px;" />
        <label for="is_published" style="font-size: 13px; font-weight: 600; color: var(--color-text);">Publicado</label>
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
                contentFields.style.display = typeSelect.value === 'quiz' ? 'none' : 'flex';
            }

            function updateYoutubePreview() {
                if (!youtubeInput || !previewWrapper || !previewFrame) return;
                const match = youtubeInput.value.trim().match(YOUTUBE_PATTERN);

                if (match) {
                    previewFrame.src = `https://www.youtube.com/embed/${match[1]}`;
                    previewWrapper.style.display = 'flex';
                } else {
                    previewFrame.src = '';
                    previewWrapper.style.display = 'none';
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
