{{--
    Campo de URL do YouTube com pré-visualização 16:9 ao vivo.

    O servidor renderiza o estado que casa com o valor persistido (watch/
    youtu.be/embed → iframe `/embed/{id}` com `dusk` do `previewDusk`);
    `LessonForm.js` (via `[data-youtube-field]`) assume a partir daí e alterna
    entre o iframe e o estado vazio a cada tecla. A pré-visualização é
    conveniência: `YoutubeSanitizerService` revalida no submit.

    Estado vazio = bloco 16:9 em pastel wash
    (`linear-gradient(135deg, var(--blue-100), var(--mint-100))`, ver
    `_youtube-field.scss`) com botão circular de play — nunca `style=`.
--}}
@props([
    'name' => 'youtube_url',
    'value' => null,
    'label' => 'URL do YouTube',
    'hint' => null,
    'previewDusk' => 'youtube-preview',
])

@php
    $id = $attributes->get('id', $name);
    $hasError = isset($errors) && $errors->has($name);

    // Mesma heurística best-effort do JS: só casa youtube.com/watch,
    // youtube.com/embed e youtu.be com id de 11 caracteres.
    $embedId = null;
    if (preg_match('/^https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$/i', (string) old($name, $value), $matches)) {
        $embedId = $matches[1];
    }
@endphp

<div data-youtube-field class="ds-field mb-3">
    <div class="form-floating">
        <input type="url"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ old($name, $value) }}"
               placeholder="https://www.youtube.com/watch?v=..."
               @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->merge(['class' => 'form-control'.($hasError ? ' is-invalid' : '')]) }} />

        <label for="{{ $id }}">
            {{ $label }}
        </label>

        @if($hasError)
            <div id="{{ $id }}-error" class="invalid-feedback d-flex align-items-center gap-1" dusk="error-{{ $name }}">
                <x-ui.icon name="info" size="14" class="flex-shrink-0" aria-hidden="true" />
                <span>{{ $errors->first($name) }}</span>
            </div>
        @endif
    </div>

    @if($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif

    <div class="mt-3" data-youtube-preview-wrapper>
        <span class="form-label d-block">Pré-visualização</span>

        <div class="ratio ratio-16x9 max-w-480 ds-youtube-preview" data-youtube-preview>
            <iframe data-youtube-frame
                    dusk="{{ $previewDusk }}"
                    @if($embedId) src="https://www.youtube.com/embed/{{ $embedId }}" @else class="d-none" @endif
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                    title="Pré-visualização do vídeo do YouTube"></iframe>

            <div class="ds-youtube-wash d-flex align-items-center justify-content-center @if($embedId) d-none @endif"
                 data-youtube-empty aria-hidden="true">
                <span class="ds-youtube-play d-inline-flex align-items-center justify-content-center">
                    <x-ui.icon name="play" size="24" aria-hidden="true" />
                </span>
            </div>
        </div>
    </div>
</div>
