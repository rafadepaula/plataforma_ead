<?php

namespace App\Services\Navigation;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
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
     *  max enrolled-course blocks rendered under "Meus
     * Cursos" before the fixed "Ver todos os cursos" child caps the list.
     */
    private const CHILDREN_LIMIT = 10;

    /**
     * @var list<string> Section labels, declared in display order.
     *
     *  `Impersonate` sits between the two: it groups the
     * Organization-scoped items a system Admin only reaches while
     * impersonating, keeping them visibly separate from the global
     * system-administration surface above it. The Aluno-facing section
     * is "Meus Cursos" — the old "Aprendizado" grouping died when each
     * enrollment became its own block .
     */
    private const SECTION_ORDER = ['Administração', 'Impersonate', 'Meus Cursos'];

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

            // ── Meus Cursos (Aluno) ──────────────────────────────────
            //
            //  a pure GROUP item (`childrenOnly`): the
            // section heading IS "Meus Cursos", so the old parent anchor
            // is redundant and never renders — every enrollment becomes
            // its own rich block, and the fixed "Ver todos os cursos"
            // child closes the list. The declared `route`/`label` stay
            // for documentation and the (unrendered) resolved shape.
            new NavigationItem(
                key: 'student-courses',
                label: 'Meus Cursos',
                route: 'student.courses.index',
                //  the parent anchor never renders (children-only),
                // so there is no highlight to compute; the per-course
                // children carry their own active flag via
                // `isChildActive()`.
                activePatterns: [],
                icon: '',
                //  neither the Admin nor the Gestor is a
                // learner: "Meus Cursos" is Aluno-only, mirroring the
                // route's own `role:aluno` middleware. Staff accounts
                // lose the section entirely, which is therefore
                // discarded by `NavigationService::build()` when empty.
                roles: ['aluno'],
                section: 'Meus Cursos',
                childrenResolver: fn ($user) => $this->resolveStudentCourseChildren($user),
                childrenOnly: true,
            ),
            //  the forum is scoped to ONE course, so no
            // generalist sidebar entry exists: it is reached from within
            // the classroom (`classroom.show`), where the `{course}`
            // context is unambiguous.
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
     *  "Meus Cursos" blocks: one per ACTIVE **or COMPLETED**
     * enrollment of the acting Aluno. Completed enrollments belong here
     * too — the certificate is issued exactly when the pivot flips to
     * `completed` (`RecalculateCourseProgress` → `CourseCompletedByStudent`),
     * so excluding them would make the certificate line unreachable
     * from the menu. `cancelled` stays out (same pivot rule as
     * `EnsureStudentIsEnrolled`). Alphabetically by course title,
     * capped at `self::CHILDREN_LIMIT` (the query fetches
     * `CHILDREN_LIMIT + 1` rows so the cap truncates without a second
     * count); the fixed "Ver todos os cursos" child is ALWAYS appended —
     * with the parent anchor gone, it is the only menu path to
     * `student.courses.index` (the "Em andamento"/"Concluídos" tabs and
     * the empty state), including for a zero-enrollment Aluno.
     *
     * `withoutGlobalScope('org')` mirrors
     * `StudentCourseController::index()`: the pivot row is the
     * enrollment boundary (each `classroom.*` route re-checks it via
     * `student.enrolled`), so the menu must not depend on `Auth::user()`
     * being resolvable by the `OrgScope` global scope.
     *
     *  the query budget is CONSTANT, never per course: the
     * enrollment fetch plus three companion queries — one grouped COUNT
     * of published lesson totals, one grouped COUNT of completed
     * lessons (the same published-lesson/non-deleted-module universe
     * `ClassroomController` counts) and ONE `certificates` fetch indexed
     * by `course_id`.
     *
     * @return list<array{key: string, label: string, url: string, course_id: int|null, progress: int|null, is_course: bool, lessons_completed: int|null, lessons_total: int|null, forum_url: string|null, certificate_url: string|null}>
     */
    private function resolveStudentCourseChildren(User $user): array
    {
        $courses = $user->courses()
            ->withoutGlobalScope('org')
            ->wherePivotIn('status', ['active', 'completed'])
            ->orderBy('courses.title')
            ->limit(self::CHILDREN_LIMIT + 1)
            ->get();

        $seeAll = [
            'key' => 'see-all',
            'label' => 'Ver todos os cursos',
            'url' => route('student.courses.index'),
            'course_id' => null,
            'progress' => null,
            'is_course' => false,
            'lessons_completed' => null,
            'lessons_total' => null,
            'forum_url' => null,
            'certificate_url' => null,
        ];

        $shown = $courses->take(self::CHILDREN_LIMIT);

        if ($shown->isEmpty()) {
            return [$seeAll];
        }

        $courseIds = $shown->pluck('id')->all();

        //  same lesson universe the classroom counts: published
        // lessons of non-deleted modules, aggregated per course in a
        // single grouped query.
        $lessonTotals = Lesson::query()
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->whereNull('modules.deleted_at')
            ->whereIn('modules.course_id', $courseIds)
            ->where('lessons.is_published', true)
            ->selectRaw('modules.course_id, count(*) as aggregate')
            ->groupBy('modules.course_id')
            ->pluck('aggregate', 'modules.course_id');

        $completedCounts = LessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->whereNull('modules.deleted_at')
            ->where('lesson_progress.user_id', $user->id)
            ->where('lesson_progress.is_completed', true)
            ->whereIn('modules.course_id', $courseIds)
            ->where('lessons.is_published', true)
            ->selectRaw('modules.course_id, count(*) as aggregate')
            ->groupBy('modules.course_id')
            ->pluck('aggregate', 'modules.course_id');

        //  ONE fetch for the whole list, indexed by course.
        // Revocation is logical (`revoked_at`), so a revoked certificate
        // filters out here the same way the classroom card treats it:
        // no line at all, never a "pending" state in the menu.
        $certificates = Certificate::query()
            ->where('user_id', $user->id)
            ->whereIn('course_id', $courseIds)
            ->whereNull('revoked_at')
            ->get(['id', 'course_id'])
            ->keyBy('course_id');

        $children = $shown
            ->map(fn (Course $course): array => [
                'key' => "course-{$course->id}",
                'label' => $course->title,
                'url' => route('classroom.show', $course),
                'course_id' => $course->id,
                'progress' => (int) ($course->pivot->progress_percentage ?? 0),
                'is_course' => true,
                'lessons_completed' => (int) ($completedCounts[$course->id] ?? 0),
                'lessons_total' => (int) ($lessonTotals[$course->id] ?? 0),
                'forum_url' => route('forum.index', $course),
                'certificate_url' => isset($certificates[$course->id])
                    ? route('certificates.download', ['certificate' => $certificates[$course->id]->id])
                    : null,
            ])
            ->all();

        $children[] = $seeAll;

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
}
