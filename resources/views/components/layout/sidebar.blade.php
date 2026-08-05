@php
    use Illuminate\Support\Arr;

    // SPEC-17 — the sidebar is driven entirely by `$navigationSections`,
    // injected by `NavigationComposer` (one filtered, URL-resolved,
    // badge-enriched list per acting user). No role checks or
    // `Route::has()` guards live here anymore: the service layer is the
    // single source of truth, so a link that the user cannot reach is
    // never present in this array (RN38/RN40).
    $sidebarSections = $navigationSections ?? [];
@endphp

<div>
    {{-- Desktop Navigation --}}
    <aside class="d-none d-lg-flex"
           style="width: 240px; background: var(--color-neutral-900); color: var(--color-neutral-400); flex-direction: column; min-height: calc(100vh - 60px); border-radius: 0px; flex-shrink: 0;">

        @foreach($sidebarSections as $section)
            <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 20px 20px 8px; font-weight: 700;">
                {{ $section->title }}
            </div>
            <nav style="display: flex; flex-direction: column;">
                @foreach($section->items as $item)
                    @php
                        $isActive = $item['active'];
                        $itemColor = $isActive ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)';
                        $itemBorder = $isActive ? 'var(--color-accent)' : 'transparent';
                        $itemBackground = $isActive ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent';
                    @endphp
                    <a href="{{ $item['url'] }}"
                       dusk="sidebar-{{ $item['key'] }}-link"
                       class="sidebar-item {{ $isActive ? 'active' : '' }}"
                       style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ $itemColor }}; border-left: 3px solid {{ $itemBorder }}; background: {{ $itemBackground }};">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                        @if($item['badge'] !== null)
                            <span class="sidebar-badge"
                                  style="margin-left: auto; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 0px; background: var(--color-accent); color: var(--color-neutral-900); font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; line-height: 1;">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </nav>
        @endforeach
    </aside>

    {{-- Mobile Drawer Slide-out --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="mobile-sidebar-backdrop d-lg-none"
         style="position: fixed; inset: 0; background: color-mix(in srgb, var(--color-neutral-900) 55%, transparent); z-index: 40; backdrop-filter: blur(1px);">
    </div>

    <aside x-show="sidebarOpen"
           x-transition:enter="transition ease-out duration-200 transform"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-150 transform"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="mobile-sidebar-drawer d-lg-none"
           style="position: fixed; left: 0; top: 0; bottom: 0; width: 280px; background: var(--color-neutral-900); color: var(--color-neutral-400); display: flex; flex-direction: column; padding: 18px 0; box-shadow: var(--shadow-lg); z-index: 50; border-radius: 0px;">
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 0 20px 18px;">
            <span style="font-family: var(--font-heading); font-weight: 800; font-size: 14px; color: var(--color-neutral-100);">
                {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
            </span>
            <button type="button" @click="sidebarOpen = false" class="btn btn-ghost btn-icon" style="color: var(--color-neutral-400); border-radius: 0px;" aria-label="Fechar menu">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>

        @auth
            <div style="display: flex; align-items: center; gap: 10px; padding: 0 20px 18px; border-bottom: 1px solid var(--color-neutral-800); margin-bottom: 12px;">
                <div class="grayscale" style="width: 32px; height: 32px; background: var(--color-neutral-800); color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; border-radius: 0px;">
                    {{ strtoupper(substr(auth()->user()->name ?? 'JP', 0, 2)) }}
                </div>
                <div>
                    <div style="font-size: 13px; font-weight: 600; color: var(--color-neutral-100);">{{ auth()->user()->name }}</div>
                    <div style="font-size: 11px; color: var(--color-neutral-400);">{{ auth()->user()->getRoleNames()->first() ?? 'Aluno' }}</div>
                </div>
            </div>
        @endauth

        <nav style="display: flex; flex-direction: column;">
            @foreach($sidebarSections as $section)
                <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: {{ $loop->first ? '0' : '12px' }} 20px 8px; font-weight: 700;">{{ $section->title }}</div>
                @foreach($section->items as $item)
                    @php
                        $isActive = $item['active'];
                        $itemColor = $isActive ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)';
                        $itemBorder = $isActive ? 'var(--color-accent)' : 'transparent';
                        $itemBackground = $isActive ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent';
                    @endphp
                    <a href="{{ $item['url'] }}"
                       dusk="sidebar-{{ $item['key'] }}-link-mobile"
                       class="sidebar-item {{ $isActive ? 'active' : '' }}"
                       style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 14px; font-weight: 600; text-decoration: none; color: {{ $itemColor }}; border-left: 3px solid {{ $itemBorder }}; background: {{ $itemBackground }};">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">{!! $item['icon'] !!}</svg>
                        <span>{{ $item['label'] }}</span>
                        @if($item['badge'] !== null)
                            <span class="sidebar-badge"
                                  style="margin-left: auto; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 0px; background: var(--color-accent); color: var(--color-neutral-900); font-size: 10px; font-weight: 800; display: inline-flex; align-items: center; justify-content: center; line-height: 1;">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>
</div>
