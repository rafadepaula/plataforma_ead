@php
    use Illuminate\Support\Facades\Route;
    $homeUrl = Route::has('student.courses.index') ? route('student.courses.index', [], false) : '#';
    $loginUrl = Route::has('login') ? route('login') : '#';
    $logoutUrl = Route::has('logout') ? route('logout') : '#';
@endphp

<header class="nav" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 24px; background: var(--color-surface); border-bottom: 1px solid var(--color-divider); height: 60px; border-radius: 0px;">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button type="button" 
                @click="sidebarOpen = !sidebarOpen" 
                class="btn btn-ghost btn-icon d-lg-none" 
                aria-label="Abrir menu"
                dusk="mobile-menu-button"
                style="color: var(--color-text); padding: 6px; border-radius: 0px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>

        <a href="{{ $homeUrl }}" style="font-family: var(--font-heading); font-weight: 800; font-size: 16px; color: var(--color-text); text-decoration: none;">
            {{ session('tenant_name') ?? config('app.name', 'Conselho EAD') }}
        </a>
    </div>

    <div class="d-none d-md-block" style="flex: 1; max-width: 400px; margin: 0 24px;">
        <div style="position: relative;">
            <svg style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); opacity: 0.5;" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" class="input" placeholder="Buscar cursos, aulas..." style="padding-left: 34px; width: 100%; height: 36px; border-radius: 0px; font-size: 13px;" />
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 12px;">
        <x-help-button :key="Route::currentRouteName() ?? 'unknown'" />

        <x-notifications-bell />

        @auth
            <div style="display: flex; align-items: center; gap: 10px; padding-left: 12px; border-left: 1px solid var(--color-divider);">
                <div class="grayscale" style="width: 32px; height: 32px; background: var(--color-neutral-800); color: var(--color-neutral-100); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 12px; border-radius: 0px;">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                </div>
                <div class="d-none d-sm-block text-start">
                    <div style="font-size: 13px; font-weight: 600; color: var(--color-text); line-height: 1.2;">
                        {{ auth()->user()->name }}
                    </div>
                    <div style="font-size: 11px; color: var(--color-neutral-600);">
                        {{ auth()->user()->getRoleNames()->first() ?? 'Usuário' }}
                    </div>
                </div>

                <form method="POST" action="{{ $logoutUrl }}" style="margin-left: 4px;">
                    @csrf
                    <button type="submit" class="btn btn-ghost" style="padding: 4px 8px; font-size: 12px; color: var(--color-neutral-600); border-radius: 0px;" title="Sair">
                        Sair
                    </button>
                </form>
            </div>
        @else
            <a href="{{ $loginUrl }}" class="btn btn-primary btn-sm" style="border-radius: 0px;">Entrar</a>
        @endauth
    </div>
</header>
