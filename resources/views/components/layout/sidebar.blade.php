@php
    use Illuminate\Support\Arr;

    // the sidebar is driven entirely by `$navigationSections`,
    // injected by `NavigationComposer` (one filtered, URL-resolved,
    // badge-enriched list per acting user). No role checks or
    // `Route::has()` guards live here anymore: the service layer is the
    // single source of truth, so a link that the user cannot reach is
    // never present in this array (RN38/RN40).
    $sidebarSections = $navigationSections ?? [];
@endphp

<div>
    {{-- Desktop Navigation --}}
    <aside class="sidebar d-none d-lg-flex">

        @foreach($sidebarSections as $section)
            {{-- `pt-0`/`pt-3x` sobrescrevem o padding-top de `.sidebar-section-title`
                 (utilities do Bootstrap saem com `!important`, então vencem a classe). --}}
            <div @class(['sidebar-section-title', 'pt-0' => $loop->first, 'pt-3x' => ! $loop->first])>
                {{ $section->title }}
            </div>
            <nav class="d-flex flex-column">
                @foreach($section->items as $item)
                    @php
                        $isActive = $item['active'];
                    @endphp
                    <a href="{{ $item['url'] }}"
                       dusk="sidebar-{{ $item['key'] }}-link"
                       class="sidebar-item {{ $isActive ? 'active' : '' }}">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
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

    {{-- Mobile Drawer — Bootstrap Offcanvas 100% declarativo.
         O gatilho vive na topbar (`data-bs-toggle="offcanvas"`,
         `data-bs-target="#mobile-sidebar"`); o backdrop é gerado pelo
         próprio Bootstrap, por isso não existe mais um
         `div.mobile-sidebar-backdrop` manual aqui. Nenhum JS de projeto
         participa deste componente. --}}
    <div class="offcanvas offcanvas-start d-lg-none text-bg-dark"
         tabindex="-1"
         id="mobile-sidebar"
         aria-labelledby="mobile-sidebar-label">
        <div class="offcanvas-header border-bottom border-secondary">
            <h2 class="offcanvas-title fw-bold fs-6" id="mobile-sidebar-label">
                {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
            </h2>
            <button type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="offcanvas"
                    aria-label="Fechar menu"></button>
        </div>

        {{-- `p-0`: os itens (`.mobile-sidebar-item`) são full-bleed e trazem
             o próprio padding; o padding padrão do offcanvas-body os recuaria. --}}
        <div class="offcanvas-body p-0 pt-3">
            @auth
                <div class="d-flex align-items-center gap-2 pb-3 px-4 border-bottom border-secondary mb-3">
                    <div class="grayscale d-flex align-items-center justify-content-center fw-bold w-32 h-32 bg-secondary">
                        {{ strtoupper(substr(auth()->user()->name ?? 'JP', 0, 2)) }}
                    </div>
                    <div>
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="text-body-secondary small">{{ auth()->user()->getRoleNames()->first() ?? 'Aluno' }}</div>
                    </div>
                </div>
            @endauth

            <nav class="d-flex flex-column">
                @foreach($sidebarSections as $section)
                    {{-- `pt-0`/`pt-3x` sobrescrevem o padding-top de `.sidebar-section-title`
                         (utilities do Bootstrap saem com `!important`, então vencem a classe). --}}
                    <div @class(['sidebar-section-title', 'pt-0' => $loop->first, 'pt-3x' => ! $loop->first])>
                        {{ $section->title }}
                    </div>
                    @foreach($section->items as $item)
                        @php
                            $isActive = $item['active'];
                        @endphp
                        <a href="{{ $item['url'] }}"
                           dusk="sidebar-{{ $item['key'] }}-link-mobile"
                           class="mobile-sidebar-item {{ $isActive ? 'active' : '' }}">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
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
