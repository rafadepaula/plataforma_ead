@php
    use Illuminate\Support\Facades\Route;

    // `$brandUrl`, `$loginUrl`, `$logoutUrl` are injected by
    // `NavigationComposer`. The brand link was previously hardcoded to
    // `student.courses.index` (always a dead `#` for an Admin/Gestor
    // who lands on the dashboard) — it is now role-aware.
    $homeUrl = $brandUrl ?? '/';
    $loginUrl = $loginUrl ?? '#';
    $logoutUrl = $logoutUrl ?? '#';
@endphp

<header class="nav d-flex align-items-center justify-content-between py-3 px-5 bg-body-secondary border-bottom h-60">
    <div class="d-flex align-items-center gap-4">
        {{-- Gatilho do drawer mobile. 100% declarativo: o Bootstrap resolve
             abertura, backdrop, foco e `aria-expanded` a partir dos
             `data-bs-*`. O alvo `#mobile-sidebar` é o `.offcanvas` de
             `components/layout/sidebar.blade.php`. --}}
        <button type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#mobile-sidebar"
                aria-controls="mobile-sidebar"
                aria-expanded="false"
                class="btn btn-link text-body text-decoration-none d-inline-flex align-items-center justify-content-center d-lg-none p-2"
                aria-label="Abrir menu"
                dusk="mobile-menu-button">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <a href="{{ $homeUrl }}" class="fw-bolder fs-5 text-body text-decoration-none lh-1">
            {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
        </a>
    </div>

    {{--  o campo de busca que ocupava este espaço foi removido:
         não pertencia a `<form>` algum, não tinha `name`/`action` e nenhum
         módulo de `resources/js/modules/` o escutava. O
         `justify-content-between` do `<header>` redistribui marca e cluster
         direito sozinho. --}}

    <div class="d-flex align-items-center gap-3">
        <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />

        <x-notifications-bell />

        {{--  sinal persistente de "Impersonate Org". `$activeOrganization`
             vem do `NavigationComposer` (via `ImpersonationContext`), a mesma
             fonte de verdade que move os itens operacionais do menu para a
             seção "Impersonate"  — nada é resolvido aqui.
             Atenção: `.badge` tem `text-transform: uppercase`
             (`resources/scss/components/_index.scss:57`), então o nome da
             Organização é RENDERIZADO em caixa alta. --}}
        @if ($activeOrganization ?? null)
            <div class="d-flex align-items-center gap-2 pe-3 border-end" dusk="topbar-impersonation">
                <x-ui.badge variant="accent-2" dusk="topbar-active-org-badge">
                    {{ $activeOrganization->name }}
                </x-ui.badge>

                <form method="POST" action="{{ route('impersonate-org.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit"
                                 variant="ghost"
                                 size="sm"
                                 class="text-decoration-underline"
                                 title="Sair do contexto"
                                 dusk="topbar-exit-impersonation">Sair do contexto</x-ui.button>
                </form>
            </div>
        @endif

        @auth
            <div class="d-flex align-items-center gap-2 ps-3 border-start">
                <div class="grayscale w-32 h-32 d-flex align-items-center justify-content-center fw-bolder small text-bg-dark text-light lh-1">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </div>
                <div class="d-none d-sm-block text-start">
                    <div class="fs-6 fw-semibold text-body lh-1">
                        {{ auth()->user()->name }}
                    </div>
                    <div class="small text-body-secondary">
                        {{ auth()->user()->getRoleNames()->first() ?? 'Usuário' }}
                    </div>
                </div>

                <a href="{{ route('profile.edit') }}" class="btn btn-link text-body text-decoration-none py-1 px-2 small text-body-secondary" dusk="topbar-profile-link">
                    Meu Perfil
                </a>

                <form method="POST" action="{{ $logoutUrl }}" class="ms-1">
                    @csrf
                    <button type="submit" class="btn btn-link text-body text-decoration-none py-1 px-2 small text-body-secondary" title="Sair">
                        Sair
                    </button>
                </form>
            </div>
        @else
            <a href="{{ $loginUrl }}" class="btn btn-primary btn-sm">Entrar</a>
        @endauth
    </div>
</header>
