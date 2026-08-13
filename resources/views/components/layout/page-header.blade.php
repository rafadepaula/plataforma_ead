{{--
    Cabeçalho de página (kicker + título + subtítulo + ações).

    Vive em `layout/` e não em `ui/` porque é peça estrutural do chrome da
    aplicação: singular por página, sempre o primeiro bloco do `@section`
    (`bootstrap-conventions` §2). Substitui o bloco `<div style="display:flex…">`
    + `<span>kicker</span>` + `<h1 style="font-family:var(--font-heading)…">`
    repetido em ~19–25 telas de index/create/edit.

    A classe `.kicker` é da camada 3 do projeto
    (`resources/scss/components/_index.scss`): o Bootstrap não emite utilities de
    `text-transform` nem de `letter-spacing`, e 11px fica abaixo de `.small`.
--}}
@props([
    'title',
    'kicker' => null,
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap align-items-center justify-content-between gap-3 mb-4']) }}>
    <div>
        @if ($kicker)
            <div class="kicker text-primary mb-1">{{ $kicker }}</div>
        @endif

        <h1 class="h3 mb-0">{{ $title }}</h1>

        @if ($subtitle)
            <p class="text-body-secondary small mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
