{{--
    Wrapper Bootstrap 5.3 para os links de paginação do Laravel.

    O componente mantém um único landmark de navegação e usa uma view interna
    de links para evitar o resumo e o `<nav>` adicionais da view padrão.

    Uso: <x-ui.pagination :paginator="$users" />
--}}
@props([
    'paginator',
    'label' => 'Paginação',
    'itemLabel' => 'itens',
])

@if ($paginator->hasPages())
    @php
        $paginatorLinks = method_exists($paginator, 'total')
            ? $paginator->onEachSide(1)->links('components.ui.pagination-links')
            : $paginator->links('components.ui.simple-pagination-links');
    @endphp

    <nav aria-label="{{ $label }}"
         {{ $attributes->merge(['class' => 'ds-pagination d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4']) }}>
        @if (method_exists($paginator, 'total'))
            <span class="ds-caption text-body-secondary">
                Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }} {{ $itemLabel }}
            </span>
        @endif

        <div class="ms-auto">
            {{ $paginatorLinks }}
        </div>
    </nav>
@endif
