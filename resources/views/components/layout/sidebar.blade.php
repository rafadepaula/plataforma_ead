@php
    use Illuminate\Support\Arr;

    // The sidebar is driven entirely by `$navigationSections`,
    // injected by `NavigationComposer` (one filtered, URL-resolved,
    // badge-enriched list per acting user).
    $sidebarSections = $navigationSections ?? [];
@endphp

<div>
    {{-- Desktop Navigation (280px fixo) --}}
    <aside class="sidebar d-none d-lg-flex">
        @foreach($sidebarSections as $section)
            <div @class(['sidebar-section-title', 'pt-0' => $loop->first])>
                {{ $section->title }}
            </div>
            <nav class="d-flex flex-column" dusk="sidebar-nav">
                @foreach($section->items as $item)
                    @php
                        $isActive = $item['active'];
                    @endphp
                    <a href="{{ $item['url'] }}"
                       dusk="sidebar-{{ $item['key'] }}-link"
                       @if($isActive) aria-current="page" @endif
                       class="sidebar-item {{ $isActive ? 'active' : '' }}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                        @if($item['badge'] !== null)
                            <span class="sidebar-badge">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endforeach
    </aside>

    {{-- Mobile Drawer — Bootstrap Offcanvas (264px suave 220ms). --}}
    <div class="offcanvas offcanvas-start d-lg-none mobile-drawer"
         tabindex="-1"
         id="mobile-sidebar"
         dusk="mobile-drawer"
         aria-labelledby="mobile-sidebar-label">
        <div class="offcanvas-header border-bottom">
            <h2 class="offcanvas-title fw-bold fs-6" id="mobile-sidebar-label">
                {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
            </h2>
            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="offcanvas"
                    aria-label="Fechar menu"></button>
        </div>

        <div class="offcanvas-body p-0 pt-3">
            @auth
                <div class="d-flex align-items-center gap-2 pb-3 px-4 border-bottom mb-3">
                    <div class="ds-avatar">
                        {{ mb_strtoupper(mb_substr(auth()->user()->name ?? 'JP', 0, 2)) }}
                    </div>
                    <div>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-body-secondary small">{{ auth()->user()->getRoleNames()->first() ?? 'Aluno' }}</div>
                    </div>
                </div>
            @endauth

            <nav class="d-flex flex-column" dusk="mobile-sidebar-nav">
                @foreach($sidebarSections as $section)
                    <div @class(['sidebar-section-title', 'pt-0' => $loop->first])>
                        {{ $section->title }}
                    </div>
                    @foreach($section->items as $item)
                        @php
                            $isActive = $item['active'];
                        @endphp
                        <a href="{{ $item['url'] }}"
                           dusk="sidebar-{{ $item['key'] }}-link-mobile"
                           @if($isActive) aria-current="page" @endif
                           class="mobile-sidebar-item {{ $isActive ? 'active' : '' }}">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                            <span>{{ $item['label'] }}</span>
                            @if($item['badge'] !== null)
                                <span class="sidebar-badge">
                                    {{ $item['badge'] }}
                                </span>
                            @endif
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </div>
    </div>
</div>
