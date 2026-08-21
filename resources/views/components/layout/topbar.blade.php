@php
    use Illuminate\Support\Facades\Route;

    // `$brandUrl`, `$loginUrl`, `$logoutUrl` are injected by
    // `NavigationComposer`. The brand link was previously hardcoded to
    // `student.courses.index` (always a dead `#` for an Admin/Gestor
    // who lands on the dashboard) — it is now role-aware.
    $homeUrl = $brandUrl ?? '/';
    $loginUrl = $loginUrl ?? '#';
    $logoutUrl = $logoutUrl ?? '#';
    $tenantName = session('tenant_name') ?? config('app.name', 'Conselho EAD');

    $brandMark = collect(preg_split('/\s+/', trim((string) $tenantName)))
        ->filter()
        ->take(2)
        ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    if ($brandMark === '') {
        $brandMark = mb_strtoupper(mb_substr((string) $tenantName, 0, 2));
    }
@endphp

<header class="appbar d-flex align-items-center justify-content-between border-bottom">
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

        {{-- Marca. `.brand-mark` (quadrado 44px, raio 14px, 800) vem de
             `resources/scss/components/_brand-mark.scss`. Em mobile só o
             quadrado aparece ("brand curta" — diretriz de mobile e responsivo). --}}
        <a href="{{ $homeUrl }}" class="d-flex align-items-center gap-3 text-decoration-none text-body">
            <span class="brand-mark" aria-hidden="true">{{ $brandMark }}</span>
            <span class="fw-bolder fs-5 text-body lh-1 d-none d-sm-inline">{{ $tenantName }}</span>
        </a>
    </div>

    {{--  o campo de busca que ocupava este espaço foi removido:
         não pertencia a `<form>` algum, não tinha `name`/`action` e nenhum
         módulo de `resources/js/modules/` o escutava. O
         `justify-content-between` do `<header>` redistribui marca e cluster
         direito sozinho. --}}

    <div class="d-flex align-items-center gap-1 gap-sm-3">
        {{--  sinal persistente de "Impersonate Org". `$activeOrganization`
             vem do `NavigationComposer` (via `ImpersonationContext`), a mesma
             fonte de verdade que move os itens operacionais do menu para a
             seção "Impersonate"  — nada é resolvido aqui.
             `x-ui.badge` NÃO aplica `text-transform`: o nome da Organização é
             renderizado com a caixa original do dado. --}}
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
                                 class="text-decoration-underline"
                                 title="Sair do contexto"
                                 dusk="topbar-exit-impersonation">Sair do contexto</x-ui.button>
                </form>
            </div>
        @endif

        <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />

        <x-notifications-bell />

        @auth
            <div class="d-flex align-items-center gap-1 ps-0 ps-sm-3 border-start">
                {{-- O círculo de iniciais é redundante com o ícone do link
                     "Meu Perfil" logo ao lado abaixo de `sm` — some para
                     caber o cluster inteiro em 320px (diretriz de mobile e responsivo). --}}
                <div class="ds-avatar d-none d-sm-flex">
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

                {{-- Abaixo de `sm` o rótulo em texto cede lugar ao ícone (o
                     cluster inteiro da app bar não cabe em 320px com o texto
                     visível — diretriz de mobile e responsivo): o nome acessível
                     continua "Meu Perfil" via `.visually-hidden`, nunca vira
                     ícone mudo. --}}
                <a href="{{ route('profile.edit') }}" class="btn btn-link text-body text-decoration-none py-1 px-2 small text-body-secondary d-inline-flex align-items-center gap-1" dusk="topbar-profile-link">
                    <x-ui.icon name="user" :size="16" class="d-sm-none" aria-hidden="true" />
                    <span class="d-none d-sm-inline">Meu Perfil</span>
                    <span class="visually-hidden d-sm-none">Meu Perfil</span>
                </a>

                <form method="POST" action="{{ $logoutUrl }}" class="ms-1">
                    @csrf
                    <button type="submit" class="btn btn-link text-body text-decoration-none py-1 px-2 small text-body-secondary d-inline-flex align-items-center gap-1" title="Sair">
                        <x-ui.icon name="log-out" :size="16" class="d-sm-none" aria-hidden="true" />
                        <span class="d-none d-sm-inline">Sair</span>
                        <span class="visually-hidden d-sm-none">Sair</span>
                    </button>
                </form>
            </div>
        @else
            <a href="{{ $loginUrl }}" class="btn btn-primary btn-sm">Entrar</a>
        @endauth
    </div>
</header>
