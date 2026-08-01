@php
    use Illuminate\Support\Facades\Route;
    $adminDashboardRoute = Route::has('admin.dashboard') ? route('admin.dashboard') : '#';
    $adminStudentsRoute = Route::has('admin.students.index') ? route('admin.students.index') : '#';
    $adminCoursesRoute = Route::has('admin.courses.index') ? route('admin.courses.index') : '#';
    $studentCoursesRoute = Route::has('student.courses.index') ? route('student.courses.index') : '#';
    $studentForumRoute = Route::has('student.forum.index') ? route('student.forum.index') : '#';
@endphp

<div>
    {{-- Desktop Navigation --}}
    <aside class="d-none d-lg-flex" 
           style="width: 240px; background: var(--color-neutral-900); color: var(--color-neutral-400); flex-direction: column; min-height: calc(100vh - 60px); border-radius: 0px; flex-shrink: 0;">
        
        @hasanyrole('admin|gestor')
            <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 20px 20px 8px; font-weight: 700;">
                Administração
            </div>
            <nav style="display: flex; flex-direction: column;">
                <a href="{{ $adminDashboardRoute }}" 
                   class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
                   style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ request()->routeIs('admin.dashboard') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('admin.dashboard') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('admin.dashboard') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }};">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ $adminStudentsRoute }}" 
                   class="sidebar-item {{ request()->routeIs('admin.students.*') ? 'active' : '' }}"
                   style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ request()->routeIs('admin.students.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('admin.students.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('admin.students.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }};">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    <span>Alunos</span>
                </a>

                <a href="{{ $adminCoursesRoute }}" 
                   class="sidebar-item {{ request()->routeIs('admin.courses.*') ? 'active' : '' }}"
                   style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ request()->routeIs('admin.courses.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('admin.courses.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('admin.courses.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }};">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    <span>Cursos e Módulos</span>
                </a>
            </nav>
        @endhasanyrole

        <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 20px 20px 8px; font-weight: 700;">
            Aprendizado
        </div>
        <nav style="display: flex; flex-direction: column;">
            <a href="{{ $studentCoursesRoute }}" 
               class="sidebar-item {{ request()->routeIs('student.courses.*') ? 'active' : '' }}"
               style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ request()->routeIs('student.courses.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('student.courses.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('student.courses.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }};">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                <span>Meus Cursos</span>
            </a>

            <a href="{{ $studentForumRoute }}" 
               class="sidebar-item {{ request()->routeIs('student.forum.*') ? 'active' : '' }}"
               style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 13px; font-weight: 600; text-decoration: none; color: {{ request()->routeIs('student.forum.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('student.forum.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('student.forum.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }};">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Fórum</span>
            </a>
        </nav>
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
            @hasanyrole('admin|gestor')
                <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 0 20px 8px; font-weight: 700;">Administração</div>
                <a href="{{ $adminDashboardRoute }}" class="sidebar-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 14px; color: {{ request()->routeIs('admin.dashboard') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('admin.dashboard') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('admin.dashboard') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }}; text-decoration: none;">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                </a>
            @endhasanyrole

            <div style="font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--color-neutral-600); padding: 12px 20px 8px; font-weight: 700;">Aprendizado</div>
            <a href="{{ $studentCoursesRoute }}" class="sidebar-item {{ request()->routeIs('student.courses.*') ? 'active' : '' }}" style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 14px; color: {{ request()->routeIs('student.courses.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('student.courses.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('student.courses.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }}; text-decoration: none;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                <span>Meus Cursos</span>
            </a>
            <a href="{{ $studentForumRoute }}" class="sidebar-item {{ request()->routeIs('student.forum.*') ? 'active' : '' }}" style="display: flex; align-items: center; gap: 12px; padding: 11px 20px; font-size: 14px; color: {{ request()->routeIs('student.forum.*') ? 'var(--color-neutral-100)' : 'var(--color-neutral-400)' }}; border-left: 3px solid {{ request()->routeIs('student.forum.*') ? 'var(--color-accent)' : 'transparent' }}; background: {{ request()->routeIs('student.forum.*') ? 'color-mix(in srgb, var(--color-accent) 18%, transparent)' : 'transparent' }}; text-decoration: none;">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                <span>Fórum</span>
            </a>
        </nav>
    </aside>
</div>
