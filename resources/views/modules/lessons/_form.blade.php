@php
    /**
     * @var \App\Models\Lesson $lesson
     */
    $type = old('type', $lesson->type ?? 'content');

    // Anexos já persistidos alimentam a lista de remoção da dropzone. O
    // `method_exists` é ponte: enquanto a relação `media()` não existir no
    // modelo, a dropzone apenas não lista anexos antigos.
    $mediaAttachments = $lesson->exists && method_exists($lesson, 'media')
        ? $lesson->media
        : collect();

    $imageAttachments = $mediaAttachments->where('kind', 'image')->values();
    $pdfAttachments = $mediaAttachments->where('kind', 'pdf')->values();

    $isPublished = old('is_published', $lesson->is_published);
    $publishHint = $isPublished
        ? 'A lição fica visível para os alunos imediatamente após salvar.'
        : 'A lição continua oculta para os alunos até ser publicada.';
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
            data-lesson-type-select
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

        <x-ui.file-drop
            name="images"
            label="Imagens"
            accept="image/*"
            :max-size="2"
            hint="PNG, JPG ou WebP"
            dusk="lesson-image-input"
            :attachments="$imageAttachments"
        />

        <x-ui.file-drop
            name="pdfs"
            label="PDFs"
            accept="application/pdf"
            :max-size="10"
            dusk="lesson-pdf-input"
            :attachments="$pdfAttachments"
        />

        <x-ui.youtube-field
            :value="$lesson->youtube_url"
            label="URL do YouTube"
            hint="O servidor revalida o link no envio: apenas vídeos do YouTube são aceitos."
            dusk="lesson-youtube-input"
            preview-dusk="youtube-preview"
        />
    </div>

    <div>
        <x-ui.switch
            name="is_published"
            label="Publicado"
            :checked="$lesson->is_published"
        />
        <p class="form-text" data-publish-hint>{{ $publishHint }}</p>
    </div>
</x-ui.field-stack>
