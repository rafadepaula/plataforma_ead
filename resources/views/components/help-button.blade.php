{{--
    SPEC-11 (RF12/RN05) — the contextual help button, mounted on every
    authenticated screen (topbar) and every public screen (Landing Page,
    `/convite/*`, `/validar-certificado/*`). The `HelpButton` component
    class already resolved `$article` (org-specific > global > null) —
    this view only renders. When `$article` is null (no content authored
    yet for this screen's `key`), the icon renders inert/disabled per
    RN05's "100% coverage may outpace content authoring" edge case: never
    a broken modal, never a 500.
--}}
@php
    $modalId = 'help-modal-'.str($key)->slug();
@endphp

@if($article)
    <button
        type="button"
        class="btn btn-ghost btn-icon"
        aria-label="Ajuda"
        data-modal-target="{{ $modalId }}"
        dusk="help-button-{{ $key }}"
        style="color: var(--color-text); border-radius: 0px;"
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </button>

    <x-ui.modal id="{{ $modalId }}" title="{{ $article->title }}" size="md">
        <div dusk="help-article-content-{{ $key }}" style="white-space: pre-wrap; font-size: 14px; line-height: 1.6;">
            {{ $article->content }}
        </div>

        <x-slot:actions>
            <button type="button" class="btn btn-ghost" data-modal-dismiss="true" style="border-radius: 0px;">Fechar</button>
        </x-slot:actions>
    </x-ui.modal>
@else
    <button
        type="button"
        class="btn btn-ghost btn-icon"
        aria-label="Ajuda indisponível"
        disabled
        dusk="help-button-{{ $key }}"
        style="color: var(--color-neutral-400); border-radius: 0px; cursor: not-allowed;"
    >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"></circle>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 2-3 4"></path>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </button>
@endif
