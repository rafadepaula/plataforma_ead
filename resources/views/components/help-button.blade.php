{{--
    the contextual help button, mounted on every
    authenticated screen (topbar) and every public screen (Landing Page,
    `/convite/*`, `/validar-certificado/*`). The `HelpButton` component
    class already resolved `$article` (org-specific > global > null) —
    this view only renders. When `$article` is null (no content authored
    yet for this screen's `key`), RN05's "100% coverage may outpace
    content authoring" edge case still applies, but a disabled/inert
    button gives the user zero feedback when clicked — it just looks
    broken. Instead this branch renders an ACTIVE button that opens a
    placeholder modal ("Estamos preparando o conteúdo de ajuda desta
    tela."), satisfying the original "never a broken modal, never a 500"
    guarantee while still telling the user something happened.
--}}
@php
    $modalId = 'help-modal-'.str($key)->slug();
@endphp

@if($article)
    <button
        type="button"
        class="btn btn-link text-body text-decoration-none d-inline-flex align-items-center gap-2"
        aria-label="Ajuda"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        dusk="help-button-{{ $key }}"
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </button>

    <x-ui.modal id="{{ $modalId }}" title="{{ $article->title }}" size="md" dusk="help-modal-{{ $key }}">
        <div dusk="help-article-content-{{ $key }}" class="fs-6 lh-lg text-prewrap">
            {{ $article->content }}
        </div>

        <x-slot:actions>
            <button type="button" class="btn btn-link text-body text-decoration-none" data-bs-dismiss="modal">Fechar</button>
        </x-slot:actions>
    </x-ui.modal>
@else
    <button
        type="button"
        class="btn btn-link text-body text-decoration-none d-inline-flex align-items-center gap-2"
        aria-label="Ajuda"
        data-bs-toggle="modal"
        data-bs-target="#{{ $modalId }}"
        dusk="help-button-{{ $key }}"
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </button>

    <x-ui.modal id="{{ $modalId }}" title="Ajuda" size="md" dusk="help-modal-{{ $key }}">
        <div dusk="help-placeholder-content-{{ $key }}" class="fs-6 lh-lg text-prewrap">
            Estamos preparando o conteúdo de ajuda desta tela.
        </div>

        <x-slot:actions>
            <button type="button" class="btn btn-link text-body text-decoration-none" data-bs-dismiss="modal">Fechar</button>
        </x-slot:actions>
    </x-ui.modal>
@endif
