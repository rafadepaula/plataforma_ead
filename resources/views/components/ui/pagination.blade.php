{{--
    Wrapper Bootstrap 5.3 para os links de paginação do Laravel.

    O markup em si (`.pagination > .page-item > .page-link`) vem das views
    `pagination::bootstrap-5` / `pagination::simple-bootstrap-5` embarcadas no
    framework e registradas como default em `AppServiceProvider::boot()`.
    Este componente só fornece o landmark de navegação, o rótulo acessível e o
    espaçamento — nunca reimplementa a lista de páginas.

    Uso: <x-ui.pagination :paginator="$users" />
--}}
@props([
    'paginator',
    'label' => 'Paginação',
])

@if ($paginator->hasPages())
    @php
        // `onEachSide()` só existe no LengthAwarePaginator; o simple paginator não tem janela.
        $paginatorLinks = method_exists($paginator, 'onEachSide')
            ? $paginator->onEachSide(1)->links()
            : $paginator->links();
    @endphp

    <nav aria-label="{{ $label }}"
         {{ $attributes->merge(['class' => 'd-flex justify-content-center mt-4']) }}>
        {{ $paginatorLinks }}
    </nav>
@endif
