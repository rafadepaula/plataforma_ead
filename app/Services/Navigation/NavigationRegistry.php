<?php

namespace App\Services\Navigation;

use App\Models\Course;
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
 * links . The official item/route/active-pattern matrix
 * is the single source of truth documented in  §3.
 */
final class NavigationRegistry
{
    /** @var list<string> */
    private const ADMIN_GESTOR = ['admin', 'gestor'];

    /**
     *  max enrolled-course shortcuts rendered under "Meus
     * Cursos" before the fixed "Ver todos os cursos" child takes over.
     */
    private const CHILDREN_LIMIT = 10;

    /**
     * @var list<string> Section labels, declared in display order.
     *
     *  `Impersonate` sits between the two: it groups the
     * Organization-scoped items a system Admin only reaches while
     * impersonating, keeping them visibly separate from the global
     * system-administration surface above it.
     */
    private const SECTION_ORDER = ['Administração', 'Impersonate', 'Aprendizado'];

    /**
     *  the "am I impersonating?" rule is shared with the topbar
     * badge, so it is owned by {@see ImpersonationContext} rather than
     * duplicated here. The default keeps the registry `new`-able from
     * unit tests that don't go through the container.
     */
    public function __construct(
        private readonly ImpersonationContext $impersonation = new ImpersonationContext,
    ) {}

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
                //  `Organizações` is reserved to system Admins; a
                // Gestor never sees this entry.
                roles: ['admin'],
                section: 'Administração',
            ),
            new NavigationItem(
                key: 'users',
                label: 'Alunos & Usuários',
                //  `users.index` is an *operational*, single-org
                // screen, and since it became Admin-exclusive
                // (`role:admin` on the route, Gestor has the dedicated
                // `students` item below) it resolves its tenant strictly
                // via `ResolvesOrgContext`: a system Admin with no
                // `org_id` and no active "Impersonate Org" cannot reach
                // it. The resolver below hides the item in that state
                // rather than offering a link that dead-ends in a
                // `back()` + "Selecione uma Organização ativa" flash
                // . The cross-org administration screen is
                // separate, future work .
                route: 'users.index',
                activePatterns: ['users.*'],
                icon: $this->usersIcon(),
                roles: ['admin'],
                section: 'Administração',
                routeResolver: fn ($user) => $this->resolveUsersRoute($user),
            ),
            //  the Gestor's exclusive Aluno directory:
            // lists only the Alunos enrolled in their own Organization's
            // Courses (`gestor.students.*`, `role:gestor`). Distinct from
            // the Admin-only `users` item above — the two are mutually
            // exclusive per role and never coexist in one menu.
            new NavigationItem(
                key: 'students',
                label: 'Alunos',
                route: 'gestor.students.index',
                activePatterns: ['gestor.students.*'],
                icon: $this->usersIcon(),
                roles: ['gestor'],
                section: 'Administração',
            ),
            // cross-org, all-roles Admin user-management
            // screen. Distinct from `users.index` above (which stays
            // operational, single-org, Admin-only): this item
            // belongs to the reduced Admin-only set  keeps in
            // "Administração", never the "Impersonate" section, so
            // `roles` is `['admin']` only (no Gestor).
            new NavigationItem(
                key: 'admin-users',
                label: 'Usuários do Sistema',
                route: 'admin.users.index',
                activePatterns: ['admin.users.*'],
                icon: $this->usersIcon(),
                roles: ['admin'],
                section: 'Administração',
            ),
            // ── Operação da Organização ──────────────────────────────
            //  the three items below act *inside* one
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
                //  badge counts attempts awaiting manual grading.
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
                //  badge counts pending forum reports.
                badgeCallback: $badges->pendingForumReportCount(...),
                sectionResolver: fn ($user) => $this->resolveOperationalSection($user),
            ),
            new NavigationItem(
                key: 'audit-logs',
                label: 'Auditoria',
                //  Audit is a system-administration surface:
                // `role:admin` on `admin.audit-logs.*` (the legacy
                // Gestor-prefixed routes were removed), so `roles`
                // mirrors that parity exactly.
                route: 'admin.audit-logs.index',
                activePatterns: ['admin.audit-logs.*'],
                icon: $this->fileTextIcon(),
                roles: ['admin'],
                section: 'Administração',
            ),
            new NavigationItem(
                key: 'settings',
                label: 'Configurações',
                //  system settings (SMTP/logo/signature) are a
                // system-administration surface: the route is `role:admin`
                // and only an Admin sees the item — the Gestor lost both
                // the link and the reachability.
                route: 'settings.edit',
                activePatterns: ['settings.*'],
                icon: $this->settingsIcon(),
                roles: ['admin'],
                section: 'Administração',
            ),

            // ── Aprendizado ──────────────────────────────────────────
            new NavigationItem(
                key: 'student-courses',
                label: 'Meus Cursos',
                route: 'student.courses.index',
                activePatterns: ['student.courses.*', 'classroom.*'],
                icon: $this->homeIcon(),
                //  neither the Admin nor the Gestor is a
                // learner: "Meus Cursos" is Aluno-only, mirroring the
                // route's own `role:aluno` middleware. Staff accounts
                // lose the "Aprendizado" section, which is therefore
                // discarded by `NavigationService::build()` when empty.
                roles: ['aluno'],
                section: 'Aprendizado',
                //  always-visible shortcut children: the
                // Aluno's active enrollments as direct classroom links.
                // The parent keeps its own URL — without active
                // enrollments the item renders without children,
                // exactly as before .
                childrenResolver: fn ($user) => $this->resolveStudentCourseChildren($user),
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
                // Aluno has no active enrollment .
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
     *  mirrors `ResolvesOrgContext::resolveOrgId()`: the item is
     * only reachable when a tenant context can be resolved server-side
     * (the Admin's impersonated `session('active_org_id')` — the screen
     * is Admin-only now, so no Gestor branch applies). Returns `null` —
     * hiding the item in both the desktop `<aside>` and the mobile
     * Offcanvas, which render the same resolved array — for a system
     * Admin in global context.
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
     *  decides where the Organization-scoped operational items
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
     *     rather than offering a dead-ending link.
     */
    private function resolveOperationalSection(User $user): ?string
    {
        if (! $user->hasRole('admin') || $user->org_id !== null) {
            return 'Administração';
        }

        //  same predicate that decides the topbar badge.
        return $this->impersonation->isImpersonating($user) ? 'Impersonate' : null;
    }

    /**
     *  the forum requires a `{course}` context. The link resolves
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

    /**
     *  "Meus Cursos" shortcut children: one per ACTIVE
     * enrollment of the acting Aluno — the same `status = active` pivot
     * rule as {@see self::resolveForumRoute()} and the "Em andamento" tab
     * of `StudentCourseController`; completed/cancelled enrollments stay
     * on `/meus-cursos` . Alphabetically by course title,
     * capped at 10 plus a fixed "Ver todos os cursos" child so a long
     * enrollment list never bloats the menu. `withoutGlobalScope('org')`
     * mirrors `StudentCourseController::index()`: the pivot row is the
     * enrollment boundary (each `classroom.*` route re-checks it via
     * `student.enrolled`), so the menu must not depend on `Auth::user()`
     * being resolvable by the `OrgScope` global scope.
     *
     * @return list<array{key: string, label: string, url: string, course_id: int|null, progress: int|null}>
     */
    private function resolveStudentCourseChildren(User $user): array
    {
        $courses = $user->courses()
            ->withoutGlobalScope('org')
            ->wherePivot('status', 'active')
            ->orderBy('courses.title')
            ->limit(self::CHILDREN_LIMIT)
            ->get();

        $children = $courses
            ->map(fn (Course $course): array => [
                'key' => "course-{$course->id}",
                'label' => $course->title,
                'url' => route('classroom.show', $course),
                'course_id' => $course->id,
                'progress' => (int) ($course->pivot->progress_percentage ?? 0),
            ])
            ->all();

        //  the fixed escape hatch to the full catalog,
        // appended only when at least one shortcut exists. `null`
        // `course_id`/`progress` marks it as a plain link to the view
        // (no active-flag matching, no progress bar).
        if ($children !== []) {
            $children[] = [
                'key' => 'see-all',
                'label' => 'Ver todos os cursos',
                'url' => route('student.courses.index'),
                'course_id' => null,
                'progress' => null,
            ];
        }

        return $children;
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
