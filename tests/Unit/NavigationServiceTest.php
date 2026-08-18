<?php

namespace Tests\Unit;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\Navigation\NavigationRegistry;
use App\Services\Navigation\NavigationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * `NavigationService::build()` is the single filter/resolution
 * gate for the sidebar/topbar. These tests assert the per-role visibility
 * matrix (RN38/RN39), RF36 (no dead `#` links), RF37 (active-pattern
 * highlighting), RF38 (badge counts) and RF39 (contextual forum URL)
 * directly against the service output, without going through Blade —
 * the {@see RoleMenuVisibilityTest} covers the rendered
 * HTML parity separately.
 */
class NavigationServiceTest extends TestCase
{
    private NavigationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NavigationService(
            new NavigationRegistry,
            Request::create('/'),
        );
    }

    /**
     * Collect a flat list of item keys for a user across all sections.
     *
     * @return list<string>
     */
    private function keysFor(User $user): array
    {
        return collect($this->service->build($user))
            ->flatMap(fn ($section) => array_column($section->items, 'key'))
            ->values()
            ->all();
    }

    /**
     * Collect the section titles emitted for a user, in display order.
     *
     * @return list<string>
     */
    private function sectionTitlesFor(User $user): array
    {
        return array_map(
            fn ($section) => $section->title,
            $this->service->build($user),
        );
    }

    /**
     * Collect the item keys of one named section (empty when the section
     * is not emitted at all).
     *
     * @return list<string>
     */
    private function keysInSection(User $user, string $title): array
    {
        $section = collect($this->service->build($user))->firstWhere('title', $title);

        return $section === null ? [] : array_column($section->items, 'key');
    }

    public function test_admin_impersonating_an_org_sees_every_administration_item_including_organizations(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        // BUG-005 — `users` is the one item that additionally requires a
        // resolvable tenant context, so impersonate an Organization here
        // and cover the context-less Admin in the two tests below.
        session(['active_org_id' => Organization::factory()->create()->id]);

        $keys = $this->keysFor($admin);

        $this->assertContains('dashboard', $keys);
        $this->assertContains('organizations', $keys);
        $this->assertContains('users', $keys);
        $this->assertContains('courses', $keys);
        $this->assertContains('quiz-attempts', $keys);
        $this->assertContains('forum-moderation', $keys);
        $this->assertContains('audit-logs', $keys);
        $this->assertContains('settings', $keys);
        // UX-001 — "Meus Cursos" was removed from the Admin's menu.
        $this->assertNotContains('student-courses', $keys);
    }

    /**
     * BUG-005 / RN38 — `users.index` resolves its tenant strictly, so a
     * system Admin in global context (no own `org_id`, no
     * `active_org_id`) cannot reach it; the item must be filtered out
     * instead of dead-ending in a `back()` + flash error.
     */
    public function test_admin_without_an_active_org_context_does_not_see_the_users_item(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $keys = $this->keysFor($admin);

        $this->assertNotContains('users', $keys);
        $this->assertContains('organizations', $keys);
        $this->assertContains('settings', $keys);
    }

    // ── UX-001 — Admin menu scope & the "Impersonate" section ────────

    /**
     * UX-001 — in global context the Admin's menu is strictly the system
     * administration surface: the operational, Organization-scoped items
     * (`courses`, `quiz-attempts`, `forum-moderation`) have no tenant to
     * act upon and must not be offered at all.
     */
    public function test_admin_without_impersonation_sees_only_the_system_administration_items(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertSame(['Administração'], $this->sectionTitlesFor($admin));
        // `users` is absent here by BUG-005 (no resolvable tenant);
        // `admin-users`  is always visible to an Admin.
        $this->assertSame(
            ['dashboard', 'organizations', 'admin-users', 'audit-logs', 'settings'],
            $this->keysInSection($admin, 'Administração'),
        );
    }

    public function test_admin_without_impersonation_has_no_impersonate_section(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertNotContains('Impersonate', $this->sectionTitlesFor($admin));

        $keys = $this->keysFor($admin);
        $this->assertNotContains('courses', $keys);
        $this->assertNotContains('quiz-attempts', $keys);
        $this->assertNotContains('forum-moderation', $keys);
    }

    /**
     * UX-001 — once an Organization is impersonated, the operational
     * items reappear, but grouped under their own "Impersonate" heading
     * so the Admin can tell system scope from Organization scope.
     */
    public function test_admin_impersonating_an_org_gets_the_operational_items_in_an_impersonate_section(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        session(['active_org_id' => Organization::factory()->create()->id]);

        $this->assertSame(
            ['courses', 'quiz-attempts', 'forum-moderation'],
            $this->keysInSection($admin, 'Impersonate'),
        );
        // The system block keeps its own items (`users` is back because
        // the impersonated Organization resolves a tenant — BUG-005;
        // `admin-users` from  is always visible to an Admin).
        $this->assertSame(
            ['dashboard', 'organizations', 'users', 'admin-users', 'audit-logs', 'settings'],
            $this->keysInSection($admin, 'Administração'),
        );
    }

    public function test_impersonate_section_is_ordered_right_after_administracao(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        session(['active_org_id' => Organization::factory()->create()->id]);

        $this->assertSame(['Administração', 'Impersonate'], $this->sectionTitlesFor($admin));
    }

    /**
     * UX-001 — RF38 badges must keep resolving after the items moved to
     * the "Impersonate" section (the badge callbacks are unchanged, but
     * the section move must not bypass `resolveBadge()`).
     */
    public function test_badges_are_still_resolved_inside_the_impersonate_section(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $this->actingAs($admin);
        session(['active_org_id' => $org->id]);

        $quiz = $this->createQuizForCourse($course);
        QuizAttempt::factory()->for($quiz)->create([
            'user_id' => $admin->id,
            'status' => 'awaiting_manual_grading',
        ]);

        $badgeItem = $this->findItem($admin, 'quiz-attempts');

        $this->assertSame('Impersonate', $badgeItem['section']);
        $this->assertSame(1, $badgeItem['badge']);
    }

    /**
     * UX-001 non-regression — a Gestor always operates inside their own
     * Organization, so nothing moves for them: the operational items stay
     * in "Administração" and no "Impersonate" heading is ever emitted.
     */
    public function test_gestor_keeps_the_operational_items_in_administracao_and_never_sees_impersonate(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->assertNotContains('Impersonate', $this->sectionTitlesFor($gestor));
        $this->assertSame(
            ['dashboard', 'users', 'courses', 'quiz-attempts', 'forum-moderation', 'audit-logs', 'settings'],
            $this->keysInSection($gestor, 'Administração'),
        );
    }

    /**
     * UX-001 — an impersonated Organization in the session must not leak
     * an "Impersonate" heading into a Gestor's menu: only a system Admin
     * (no own `org_id`) can be in an impersonated context.
     */
    public function test_a_stale_active_org_id_never_creates_an_impersonate_section_for_a_gestor(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        session(['active_org_id' => $org->id]);

        $this->assertNotContains('Impersonate', $this->sectionTitlesFor($gestor));
        $this->assertContains('courses', $this->keysInSection($gestor, 'Administração'));
    }

    /**
     * UX-001 — a dual Admin/Gestor account bound to its own Organization
     * is not impersonating anything: it operates in its own tenant, so
     * the operational items stay in "Administração".
     */
    public function test_admin_with_an_own_org_id_keeps_the_operational_items_in_administracao(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => $org->id]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertNotContains('Impersonate', $this->sectionTitlesFor($admin));
        $this->assertContains('courses', $this->keysInSection($admin, 'Administração'));
    }

    /**
     * UX-001 — "Meus Cursos" is gone from the Admin's menu, which leaves
     * the "Aprendizado" section empty and therefore dropped entirely by
     * `build()`, in both contexts.
     */
    public function test_admin_never_sees_the_aprendizado_section(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertNotContains('Aprendizado', $this->sectionTitlesFor($admin));
        $this->assertNotContains('student-courses', $this->keysFor($admin));

        session(['active_org_id' => Organization::factory()->create()->id]);

        $this->assertNotContains('Aprendizado', $this->sectionTitlesFor($admin));
        $this->assertNotContains('student-courses', $this->keysFor($admin));
    }

    /**
     * UX-001 non-regression — only the Admin loses "Meus Cursos"; the
     * Gestor and the Aluno keep their "Aprendizado" section.
     */
    public function test_gestor_and_aluno_still_see_meus_cursos(): void
    {
        $org = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->assertContains('Aprendizado', $this->sectionTitlesFor($gestor));
        $this->assertContains('student-courses', $this->keysInSection($gestor, 'Aprendizado'));

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->assertContains('Aprendizado', $this->sectionTitlesFor($aluno));
        $this->assertContains('student-courses', $this->keysInSection($aluno, 'Aprendizado'));
    }

    public function test_admin_impersonating_an_org_sees_the_users_item_again(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        session(['active_org_id' => $org->id]);

        $this->assertContains('users', $this->keysFor($admin));
    }

    public function test_gestor_never_sees_organizations_but_sees_everything_else_admin_sees(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $keys = $this->keysFor($gestor);

        // RN39 — `organizations` is admin-exclusive.
        $this->assertNotContains('organizations', $keys);
        $this->assertContains('dashboard', $keys);
        $this->assertContains('users', $keys);
        $this->assertContains('courses', $keys);
        $this->assertContains('settings', $keys);
    }

    public function test_aluno_sees_no_administration_block_at_all(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $sections = $this->service->build($aluno);

        // RN38 — the entire Administração section must not be rendered.
        foreach ($sections as $section) {
            $this->assertNotSame('Administração', $section->title, 'Aluno must never see the Administração section.');
        }
    }

    public function test_aluno_with_no_enrollments_has_no_forum_link(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $keys = $this->keysFor($aluno);

        $this->assertNotContains('forum', $keys);
        $this->assertContains('student-courses', $keys);
    }

    public function test_aluno_with_an_active_enrollment_sees_the_forum_linked_to_that_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $sections = $this->service->build($aluno);
        $forumItem = collect($sections)
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'forum');

        $this->assertNotNull($forumItem, 'Forum link must render when the Aluno has an active enrollment.');
        $this->assertSame(route('forum.index', $course), $forumItem['url']);
    }

    public function test_admin_audit_logs_route_resolves_to_admin_prefixed_route(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $auditItem = $this->findItem($admin, 'audit-logs');

        $this->assertSame(route('admin.audit-logs.index'), $auditItem['url']);
    }

    public function test_gestor_only_audit_logs_route_resolves_to_gestor_prefixed_route(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $auditItem = $this->findItem($gestor, 'audit-logs');

        $this->assertSame(route('gestor.audit-logs.index'), $auditItem['url']);
    }

    public function test_pending_essay_badge_counts_awaiting_manual_grading_attempts(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestor);

        $quiz = $this->createQuizForCourse($course);
        QuizAttempt::factory()->for($quiz)->create([
            'user_id' => $gestor->id,
            'status' => 'awaiting_manual_grading',
        ]);
        QuizAttempt::factory()->for($quiz)->create([
            'user_id' => $gestor->id,
            'status' => 'graded',
        ]);

        $badgeItem = $this->findItem($gestor, 'quiz-attempts');

        $this->assertSame(1, $badgeItem['badge']);
    }

    public function test_zero_pending_count_renders_no_badge(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $badgeItem = $this->findItem($gestor, 'quiz-attempts');

        $this->assertNull($badgeItem['badge']);
    }

    public function test_pending_forum_report_badge_counts_only_pending_reports(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestor);

        $topicA = ForumTopic::factory()->for($course)->for($gestor, 'user')->create(['org_id' => $org->id]);
        $topicB = ForumTopic::factory()->for($course)->for($gestor, 'user')->create(['org_id' => $org->id]);
        $topicC = ForumTopic::factory()->for($course)->for($gestor, 'user')->create(['org_id' => $org->id]);

        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicA->id,
            'reported_by' => $gestor->id,
            'status' => 'pending',
        ]);
        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicB->id,
            'reported_by' => $gestor->id,
            'status' => 'pending',
        ]);
        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $topicC->id,
            'reported_by' => $gestor->id,
            'status' => 'reviewed_dismissed',
        ]);

        $badgeItem = $this->findItem($gestor, 'forum-moderation');

        $this->assertSame(2, $badgeItem['badge']);
    }

    public function test_pending_forum_report_badge_never_leaks_another_orgs_reports(): void
    {
        // RN41/security — `ForumReport` has no `OrgScope`; the badge must
        // still only count reports whose target post the acting Gestor can
        // `view` (same-org), never a foreign org's pending reports.
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $gestorA = User::factory()->create(['org_id' => $orgA->id]);
        $gestorA->assignRole(RolesEnum::GESTOR->value);
        $this->actingAs($gestorA);

        $ownTopic = ForumTopic::factory()->for($courseA)->for($gestorA, 'user')->create(['org_id' => $orgA->id]);
        $foreignTopic = ForumTopic::factory()->for($courseB)->for($gestorA, 'user')->create(['org_id' => $orgB->id]);

        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $ownTopic->id,
            'reported_by' => $gestorA->id,
            'status' => 'pending',
        ]);
        ForumReport::factory()->create([
            'postable_type' => ForumTopic::class,
            'postable_id' => $foreignTopic->id,
            'reported_by' => $gestorA->id,
            'status' => 'pending',
        ]);

        $badgeItem = $this->findItem($gestorA, 'forum-moderation');

        $this->assertSame(1, $badgeItem['badge'], 'Gestor must not see a foreign org pending report count.');
    }

    public function test_active_flag_is_true_when_request_route_matches_an_active_pattern(): void
    {
        $org = Organization::factory()->create();
        // A Gestor always resolves a tenant context, so the `users` item
        // is present for them (BUG-005 hides it only for a context-less
        // Admin).
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        // `routeIs()` reads `request()->route()->named(...)`. In a unit
        // test no route is dispatched, so a real `Illuminate\Routing\Route`
        // is bound to the request via `setRouteResolver()` — exercising
        // the active wildcard matching (RF37) against the framework's own
        // `named()` implementation, not a re-implementation of it. The
        // Feature/Dusk suites additionally cover dispatched-route parity.
        $route = (new Route('GET', 'users/create', []))
            ->name('users.create');

        $request = Request::create('/users/create', 'GET');
        $request->setRouteResolver(fn () => $route);

        $service = new NavigationService(new NavigationRegistry, $request);

        $usersItem = collect($service->build($gestor))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'users');

        // RF37 — the `users.*` wildcard highlights the parent on a
        // sub-route like `users.create`.
        $this->assertTrue($usersItem['active']);
    }

    public function test_guest_returns_an_empty_section_list(): void
    {
        $this->assertSame([], $this->service->build(null));
    }

    public function test_every_resolved_url_is_a_real_registered_route_never_a_dead_hash(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);
        $admin->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $items = collect($this->service->build($admin))->flatMap(fn ($section) => $section->items);

        foreach ($items as $item) {
            $this->assertNotSame('#', $item['url'], "Item {$item['key']} resolved to a dead '#' link.");
            $this->assertStringStartsWith('http', $item['url'], "Item {$item['key']} URL is not absolute.");
        }
    }

    /**
     * @return array{key: string, label: string, url: string, active: bool, badge: int|string|null, icon: string, section: string}
     */
    private function findItem(User $user, string $key): array
    {
        $item = collect($this->service->build($user))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', $key);

        $this->assertNotNull($item, "Expected to find navigation item with key [{$key}].");

        return $item;
    }

    /**
     * Build the module → lesson → quiz chain for a course so an essay
     * `QuizAttempt` can be attached to a real `org_id`-scoped course.
     */
    private function createQuizForCourse(Course $course): Quiz
    {
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);

        return Quiz::factory()->for($lesson)->create();
    }
}
