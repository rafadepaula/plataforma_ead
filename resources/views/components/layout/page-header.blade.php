{{--
    Cabeçalho de página (breadcrumb + kicker + título + subtítulo + ações).

    Vive em `layout/` e não em `ui/` porque é peça estrutural do chrome da
    aplicação: singular por página, sempre o primeiro bloco do `@section`
    (`bootstrap-conventions` §2). Substitui o bloco `<div style="display:flex…">`
    + `<span>kicker</span>` + `<h1 style="font-family:var(--font-heading)…">`
    repetido em ~19–25 telas de index/create/edit.

    `kicker` renderiza com `.ds-overline` (uppercase + tracking, camada de
    tokens globais de `_ds/.../tokens/typography.css`) tingido de
    `text-primary` — é o "overline azul" da diretriz de shell e navegação. É o
    único lugar deliberado de caixa-alta além de `.ds-overline` em si; este
    prop `kicker` não usa a classe `.kicker` — essa classe segue definida em
    `_page-header.scss` e em uso ativo por `x-ui.stat-card` e outras telas,
    não deve ser removida.
    `subtitle` renderiza com `.ds-lead` (19px). `breadcrumb` é opcional e
    aditivo — telas antigas sem o prop continuam funcionando sem quebra.

    Convenção de `$actions`: no máximo 1 ação `primary` + N ações tonais
    (`ghost`/`outline`) — não há enforcement em Blade, é só a convenção do
    slot (ver diretriz da biblioteca de componentes).
--}}
@props([
    'title',
    'kicker' => null,
    'subtitle' => null,
    'breadcrumb' => null,
])

<div {{ $attributes->merge(['class' => 'd-flex flex-wrap align-items-center justify-content-between gap-3 mb-4']) }}>
    <div>
        @if ($breadcrumb)
            <nav aria-label="breadcrumb" class="ds-caption d-flex align-items-center flex-wrap gap-1 mb-2">
                @foreach ($breadcrumb as $index => $crumb)
                    @if ($index > 0)
                        <x-ui.icon name="chevron-right" size="16" class="text-body-secondary" />
                    @endif

                    @if (! empty($crumb['url']) && $index < count($breadcrumb) - 1)
                        <a href="{{ $crumb['url'] }}" class="ds-muted text-decoration-none">{{ $crumb['label'] }}</a>
                    @else
                        <span class="ds-muted">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif

        @if ($kicker)
            <div class="ds-overline text-primary mb-1">{{ $kicker }}</div>
        @endif

        <h1 class="h3 mb-0">{{ $title }}</h1>

        @if ($subtitle)
            <p class="ds-lead mb-0 mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="d-flex flex-wrap gap-2">{{ $actions }}</div>
    @endisset
</div>
