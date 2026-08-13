<?php

namespace App\Services\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Declarative configuration of every sidebar/topbar menu item, authored
 * once and read-only thereafter. The {@see NavigationService} is the
 * only consumer: it filters this list per-acting-user, resolves URLs
 * and compiles the visible {@see NavigationSection}s handed to Blade.
 *
 * Route names here MUST mirror the registered `routes/web.php` names —
 * never the legacy `admin.students.index` / `admin.courses.index` /
 * `student.forum.index` names that previously degraded to dead `#`
 * links (SPEC-17 RF36). The official item/route/active-pattern matrix
 * is the single source of truth documented in SPEC-17 §3.
 */
final class NavigationRegistry
{
    /** @var list<string> */
    private const ADMIN_GESTOR = ['admin', 'gestor'];

    /**
     * @var list<string> Section labels, declared in display order.
     *
     * UX-001 — `Impersonate` sits between the two: it groups the
     * Organization-scoped items a system Admin only reaches while
     * impersonating, keeping them visibly separate from the global
     * system-administration surface above it.
     */
    private const SECTION_ORDER = ['Administração', 'Impersonate', 'Aprendizado'];

    /**
     * @return list<NavigationItem>
     */
    public function items(): array
    {
        $badges = new NavigationBadges;

        return [
            // ── Administração ────────────────────────────────────────
            new NavigationItem(
                key: 'dashboard',
                label: 'Dashboard',
                route: 'admin.dashboard',
                activePatterns: ['admin.dashboard'],
                icon: $this->dashboardIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
            ),
            new NavigationItem(
                key: 'organizations',
                label: 'Organizações',
                route: 'organizations.index',
                activePatterns: ['organizations.*'],
                icon: $this->buildingsIcon(),
                // RN39 — `Organizações` is reserved to system Admins; a
                // Gestor never sees this entry.
                roles: ['admin'],
                section: 'Administração',
            ),
            new NavigationItem(
                key: 'users',
                label: 'Alunos & Usuários',
                // BUG-005 — `users.index` is an *operational*, single-org
                // screen: `UserController` resolves its tenant strictly
                // via `ResolvesOrgContext`, so a system Admin with no
                // `org_id` and no active "Impersonate Org" cannot reach
                // it. The resolver below hides the item in that state
                // rather than offering a link that dead-ends in a
                // `back()` + "Selecione uma Organização ativa" flash
                // (RN38/RN40). The cross-org administration screen is
                // separate, future work (SPEC-002).
                route: 'users.index',
                activePatterns: ['users.*'],
                icon: $this->usersIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
                routeResolver: fn ($user) => $this->resolveUsersRoute($user),
            ),
            // ── Operação da Organização ──────────────────────────────
            // UX-001 — the three items below act *inside* one
            // Organization. For a Gestor that is always their own tenant,
            // so they stay in "Administração"; for a system Admin they
            // only mean something under an active "Impersonate Org",
            // where they are grouped in their own section (and are hidden
            // outright in global context). See `resolveOperationalSection()`.
            new NavigationItem(
                key: 'courses',
                label: 'Cursos e Módulos',
                route: 'courses.index',
                activePatterns: ['courses.*', 'modules.*', 'lessons.*', 'quizzes.*'],
                icon: $this->bookIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
                sectionResolver: fn ($user) => $this->resolveOperationalSection($user),
            ),
            new NavigationItem(
                key: 'quiz-attempts',
                label: 'Redações Pendentes',
                route: 'quiz-attempts.pending',
                activePatterns: ['quiz-attempts.*'],
                icon: $this->clipboardIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
                // RF38 — badge counts attempts awaiting manual grading.
                badgeCallback: $badges->pendingEssayCount(...),
                sectionResolver: fn ($user) => $this->resolveOperationalSection($user),
            ),
            new NavigationItem(
                key: 'forum-moderation',
                label: 'Moderação do Fórum',
                route: 'forum-moderation.index',
                activePatterns: ['forum-moderation.*'],
                icon: $this->shieldIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
                // RF38 — badge counts pending forum reports.
                badgeCallback: $badges->pendingForumReportCount(...),
                sectionResolver: fn ($user) => $this->resolveOperationalSection($user),
            ),
            new NavigationItem(
                key: 'audit-logs',
                label: 'Auditoria',
                // The concrete route name is decided per-user by the
                // resolver below (RN39): `admin.audit-logs.*` for an
                // Admin, `gestor.audit-logs.*` for a Gestor-only user.
                route: 'admin.audit-logs.index',
                activePatterns: ['admin.audit-logs.*', 'gestor.audit-logs.*'],
                icon: $this->fileTextIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
                routeResolver: fn ($user) => $this->resolveAuditLogsRoute($user),
            ),
            new NavigationItem(
                key: 'settings',
                label: 'Configurações',
                route: 'settings.edit',
                activePatterns: ['settings.*'],
                icon: $this->settingsIcon(),
                roles: self::ADMIN_GESTOR,
                section: 'Administração',
            ),

            // ── Aprendizado ──────────────────────────────────────────
            new NavigationItem(
                key: 'student-courses',
                label: 'Meus Cursos',
                route: 'student.courses.index',
                activePatterns: ['student.courses.*', 'classroom.*'],
                icon: $this->homeIcon(),
                // UX-001 — the Admin is not a learner: "Meus Cursos" was
                // dropped from their menu, which leaves "Aprendizado"
                // empty and therefore discarded by
                // `NavigationService::build()`.
                roles: ['aluno', 'gestor'],
                section: 'Aprendizado',
            ),
            new NavigationItem(
                key: 'forum',
                label: 'Fórum de Dúvidas',
                // The forum lives under `courses/{course}/forum`, so
                // there is no single canonical URL — the contextual
                // `routeResolver` below is the sole source of the href
                // (the `route` field is inert when a resolver is set).
                // It returns the most recently accessed enrolled course's
                // forum, or `null` to hide the item entirely when the
                // Aluno has no active enrollment (RF39/RN38).
                route: 'forum.index',
                activePatterns: ['forum.*', 'forum-replies.*'],
                icon: $this->messageIcon(),
                roles: ['aluno'],
                section: 'Aprendizado',
                routeResolver: fn ($user) => $this->resolveForumRoute($user),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public function sectionOrder(): array
    {
        return self::SECTION_ORDER;
    }

    /**
     * RN39 — Admin (or a dual Admin/Gestor account) routes to the global
     * `admin.audit-logs.index`; a Gestor-only account routes to the
     * scoped `gestor.audit-logs.index`. Returns `null` if neither route
     * name is registered (hides the item rather than emitting a dead
     * link, mirroring the legacy `Route::has()` guard).
     */
    private function resolveAuditLogsRoute(User $user): ?string
    {
        $isAdmin = $user->hasRole('admin');
        $isGestorOnly = $user->hasRole('gestor') && ! $isAdmin;

        if ($isGestorOnly && Route::has('gestor.audit-logs.index')) {
            return route('gestor.audit-logs.index');
        }

        if ($isAdmin && Route::has('admin.audit-logs.index')) {
            return route('admin.audit-logs.index');
        }

        return null;
    }

    /**
     * BUG-005 — mirrors `ResolvesOrgContext::resolveOrgId()`: the item is
     * only reachable when a tenant context can be resolved server-side
     * (the Gestor's own `org_id`, or the Admin's impersonated
     * `session('active_org_id')`). Returns `null` — hiding the item in
     * both the desktop `<aside>` and the mobile Offcanvas, which render
     * the same resolved array — for a system Admin in global context.
     */
    private function resolveUsersRoute(User $user): ?string
    {
        $orgId = $user->org_id ?? session('active_org_id');

        if (! $orgId) {
            return null;
        }

        return route('users.index');
    }

    /**
     * UX-001 — decides where the Organization-scoped operational items
     * ("Cursos e Módulos", "Redações Pendentes", "Moderação do Fórum")
     * belong for the acting user:
     *
     *  - anyone bound to their own Organization (a Gestor, or a dual
     *    Admin/Gestor account with an `org_id`) always operates in that
     *    tenant → they stay in "Administração", unchanged;
     *  - a system Admin (no own `org_id`) only reaches these screens
     *    through an "Impersonate Org" → they move to the "Impersonate"
     *    section, which disappears with the context;
     *  - a system Admin in global context has no tenant to act upon →
     *    `null` hides the items entirely, the same way
     *    {@see self::resolveUsersRoute()} hides "Alunos & Usuários"
     *    (BUG-005) rather than offering a dead-ending link.
     */
    private function resolveOperationalSection(User $user): ?string
    {
        if (! $user->hasRole('admin') || $user->org_id !== null) {
            return 'Administração';
        }

        return session('active_org_id') ? 'Impersonate' : null;
    }

    /**
     * RF39 — the forum requires a `{course}` context. The link resolves
     * to the most recently accessed enrolled course's forum if one
     * exists, otherwise to `student.courses.index` as a course selector.
     * Returns `null` (hiding the item) only for an Aluno with zero
     * active enrollments.
     */
    private function resolveForumRoute(User $user): ?string
    {
        $course = $user->courses()
            ->wherePivot('status', 'active')
            ->latest('course_user.updated_at')
            ->first();

        if ($course === null) {
            return null;
        }

        return route('forum.index', $course);
    }

    // ── Icons (Modernist Design System — inline SVG, 17×17) ──────────

    private function dashboardIcon(): string
    {
        return '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>';
    }

    private function buildingsIcon(): string
    {
        return '<path d="M3 21h18"></path><path d="M5 21V7l8-4v18"></path><path d="M19 21V11l-6-4"></path><path d="M9 9v0"></path><path d="M9 12v0"></path><path d="M9 15v0"></path><path d="M9 18v0"></path>';
    }

    private function usersIcon(): string
    {
        return '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>';
    }

    private function bookIcon(): string
    {
        return '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>';
    }

    private function clipboardIcon(): string
    {
        return '<path d="M9 2h6a1 1 0 0 1 1 1v2H8V3a1 1 0 0 1 1-1z"></path><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><path d="M9 12h6"></path><path d="M9 16h6"></path>';
    }

    private function shieldIcon(): string
    {
        return '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12h6"></path>';
    }

    private function fileTextIcon(): string
    {
        return '<path d="M9 12h6"></path><path d="M9 16h6"></path><path d="M9 8h1"></path><path d="M14 3v4a1 1 0 0 0 1 1h4"></path><path d="M5 3h9l5 5v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"></path>';
    }

    private function settingsIcon(): string
    {
        return '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>';
    }

    private function homeIcon(): string
    {
        return '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>';
    }

    private function messageIcon(): string
    {
        return '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>';
    }
}
