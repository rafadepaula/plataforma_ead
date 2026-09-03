{{--
    Campo de vídeo por provedor (YouTube | Vimeo) com pré-visualização 16:9
    ao vivo.

    O servidor renderiza o estado que casa com o valor persistido — detecta o
    provedor pela própria URL (mesma heurística best-effort do JS e dos
    sanitizadores) e monta o embed correspondente; `LessonForm.js` (via
    `[data-video-field]`) assume a partir daí, alternando select de provedor,
    iframe e estado vazio a cada tecla. A pré-visualização é conveniência: o
    `VideoUrlSanitizerManager` revalida no submit.

    Estado vazio = bloco 16:9 em pastel wash (`.ds-video-wash`) com botão
    circular de play — nunca `style=`.
--}}
@props([
    'name' => 'video_url',
    'providerName' => 'video_provider',
    'value' => null,
    'provider' => null,
    'label' => 'URL do vídeo',
    'hint' => null,
    'previewDusk' => 'video-preview',
])

@php
    $id = $attributes->get('id', $name);
    $hasError = isset($errors) && $errors->has($name);

    $currentUrl = trim((string) old($name, $value));
    $currentProvider = old($providerName, $provider);

    // Mesma heurística best-effort do LessonForm.js: casa as formas aceitas
    // pelos sanitizadores e monta o embed de preview por provedor. Quando a
    // URL já identifica o provedor e o formulário não veio de um erro
    // (old vazio), o select segue a detecção.
    $previewSrc = null;
    if (preg_match('/^https?:\/\/(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/)|youtube-nocookie\.com\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$/i', $currentUrl, $matches)) {
        $previewSrc = 'https://www.youtube-nocookie.com/embed/'.$matches[1];
        $currentProvider = $currentProvider ?: 'youtube';
    } elseif (preg_match('/^https?:\/\/(?:www\.)?(?:player\.)?vimeo\.com\/(?:video\/)?(\d{6,})(?:\/([A-Za-z0-9]+))?(?:[?&][^\s]*)?$/i', $currentUrl, $matches)) {
        parse_str((string) (parse_url($currentUrl, PHP_URL_QUERY) ?? ''), $query);
        $hash = $matches[2] ?? (is_string($query['h'] ?? null) && $query['h'] !== '' ? $query['h'] : null);
        $previewSrc = 'https://player.vimeo.com/video/'.$matches[1].($hash !== null && $hash !== '' ? '?h='.$hash : '');
        $currentProvider = $currentProvider ?: 'vimeo';
    }

    $providerOptions = [
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
    ];
@endphp

<div data-video-field class="ds-field mb-3">
    <div class="mb-3">
        <label for="{{ $providerName }}" class="form-label">Provedor do vídeo</label>
        <select id="{{ $providerName }}"
                name="{{ $providerName }}"
                class="form-select"
                data-video-provider-select
                dusk="lesson-provider-select">
            @foreach($providerOptions as $providerValue => $providerLabel)
                <option value="{{ $providerValue }}" @selected($currentProvider === $providerValue)>{{ $providerLabel }}</option>
            @endforeach
        </select>
    </div>

    <div class="form-floating">
        <input type="url"
               id="{{ $id }}"
               name="{{ $name }}"
               value="{{ old($name, $value) }}"
               placeholder="https://..."
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

    <div class="mt-3" data-video-preview-wrapper>
        <span class="form-label d-block">Pré-visualização</span>

        <div class="ratio ratio-16x9 max-w-480" data-video-preview>
            <iframe data-video-frame
                    dusk="{{ $previewDusk }}"
                    @if($previewSrc) src="{{ $previewSrc }}" @else class="d-none" @endif
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                    title="Pré-visualização do vídeo"></iframe>

            <div class="ds-video-wash d-flex align-items-center justify-content-center @if($previewSrc) d-none @endif"
                 data-video-empty aria-hidden="true">
                <span class="ds-video-play d-inline-flex align-items-center justify-content-center">
                    <x-ui.icon name="play" size="24" aria-hidden="true" />
                </span>
            </div>
        </div>
    </div>
</div>
