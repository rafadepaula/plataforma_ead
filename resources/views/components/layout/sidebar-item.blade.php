{{--
    x-layout.sidebar-item — um item do menu lateral com seus subitens
    (children) sempre visíveis abaixo dele. Compartilhado entre o `<aside>`
    desktop (`.sidebar-item`) e o Offcanvas mobile (`.mobile-sidebar-item`):
    `:mobile` troca a classe base e o sufixo `-mobile` dos dusk selectors,
    garantindo que os dois renders nunca divergam.

    Props:
      - item   array  item resolvido por `NavigationService::resolve()`:
                       {key, label, url, active, badge, icon, section, children},
                       onde cada child é
                       {key, label, url, active, progress} (progress `null`
                       marca um child sintético sem barra, como "Ver todos").
      - mobile bool   (false) render mobile — classe `.mobile-sidebar-item`
                       e dusk selectors com sufixo `-mobile`.
--}}
@props([
    'item',
    'mobile' => false,
])

@php
    $itemClass = $mobile ? 'mobile-sidebar-item' : 'sidebar-item';
    $duskSuffix = $mobile ? '-mobile' : '';
@endphp

<a href="{{ $item['url'] }}"
   dusk="sidebar-{{ $item['key'] }}-link{{ $duskSuffix }}"
   @if($item['active']) aria-current="page" @endif
   class="{{ $itemClass }} {{ $item['active'] ? 'active' : '' }}">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
    <span>{{ $item['label'] }}</span>
    @if($item['badge'] !== null)
        <span class="sidebar-badge">
            {{ $item['badge'] }}
        </span>
    @endif
</a>

@if($item['children'] !== [])
    <div class="sidebar-children">
        @foreach($item['children'] as $child)
            <a href="{{ $child['url'] }}"
               dusk="sidebar-{{ $child['key'] }}-link{{ $duskSuffix }}"
               @if($child['active']) aria-current="page" @endif
               class="sidebar-child-item {{ $child['active'] ? 'active' : '' }}">
                <span class="d-block text-truncate">{{ $child['label'] }}</span>
                @if($child['progress'] !== null)
                    <x-ui.progress :value="$child['progress']" :height="4" :label="'Progresso: '.$child['label']" />
                @endif
            </a>
        @endforeach
    </div>
@endif
