<?php

namespace Tests\Unit;

use App\Enums\Permissions\RolesEnum;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ForumReport;
use App\Models\ForumTopic;
use App\Models\Lesson;
use App\Models\LessonProgress;
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
 * matrix ,  (no dead `#` links),  (active-pattern
 * highlighting),  (badge counts) and  (contextual forum URL)
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
        //  `users` is the one item that additionally requires a
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
        //  "Meus Cursos" was removed from the Admin's menu.
        $this->assertNotContains('student-courses', $keys);
    }

    /**
     * `users.index` resolves its tenant strictly, so a
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

    // ──  Admin menu scope & the "Impersonate" section ────────

    /**
     *  in global context the Admin's menu is strictly the system
     * administration surface: the operational, Organization-scoped items
     * (`courses`, `quiz-attempts`, `forum-moderation`) have no tenant to
     * act upon and must not be offered at all.
     */
    public function test_admin_without_impersonation_sees_only_the_system_administration_items(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertSame(['Administração'], $this->sectionTitlesFor($admin));
        // `users` is absent here by  (no resolvable tenant);
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
     *  once an Organization is impersonated, the operational
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
        // the impersonated Organization resolves a tenant — ;
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
     *   badges must keep resolving after the items moved to
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
     *  non-regression — a Gestor always operates inside their own
     * Organization, so nothing moves for them: the operational items stay
     * in "Administração" and no "Impersonate" heading is ever emitted.
     * `users`/`audit-logs` are Admin-only now; `students` is the
     * Gestor-exclusive people-management item.
     */
    public function test_gestor_keeps_the_operational_items_in_administracao_and_never_sees_impersonate(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->assertNotContains('Impersonate', $this->sectionTitlesFor($gestor));
        $this->assertSame(
            ['dashboard', 'students', 'courses', 'quiz-attempts', 'forum-moderation'],
            $this->keysInSection($gestor, 'Administração'),
        );
    }

    /**
     *  an impersonated Organization in the session must not leak
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
     *  a dual Admin/Gestor account bound to its own Organization
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
     *  "Meus Cursos" is gone from the Admin's menu, which leaves
     * the section empty and therefore dropped entirely by `build()`, in
     * both contexts.
     */
    public function test_admin_never_sees_the_meus_cursos_section(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->assertNotContains('Meus Cursos', $this->sectionTitlesFor($admin));
        $this->assertNotContains('student-courses', $this->keysFor($admin));

        session(['active_org_id' => Organization::factory()->create()->id]);

        $this->assertNotContains('Meus Cursos', $this->sectionTitlesFor($admin));
        $this->assertNotContains('student-courses', $this->keysFor($admin));
    }

    /**
     *  "Meus Cursos" is Aluno-only (`role:aluno` parity — staff
     * accounts are not learners): the Gestor's section is empty and
     * therefore dropped entirely by `build()`, while the Aluno keeps
     * both the section and the group item.
     */
    public function test_only_the_aluno_still_sees_meus_cursos(): void
    {
        $org = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);
        $this->assertNotContains('Meus Cursos', $this->sectionTitlesFor($gestor));
        $this->assertNotContains('student-courses', $this->keysFor($gestor));

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->assertContains('Meus Cursos', $this->sectionTitlesFor($aluno));
        $this->assertContains('student-courses', $this->keysInSection($aluno, 'Meus Cursos'));
    }

    public function test_admin_impersonating_an_org_sees_the_users_item_again(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        session(['active_org_id' => $org->id]);

        $this->assertContains('users', $this->keysFor($admin));
    }

    public function test_gestor_never_sees_the_admin_exclusive_items_but_sees_the_students_item(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $keys = $this->keysFor($gestor);

        //  `organizations`, `users`, `audit-logs` and
        // `settings` are admin-exclusive; "Meus Cursos" is Aluno-only.
        $this->assertNotContains('organizations', $keys);
        $this->assertNotContains('users', $keys);
        $this->assertNotContains('audit-logs', $keys);
        $this->assertNotContains('settings', $keys);
        $this->assertNotContains('student-courses', $keys);
        //  the Gestor-exclusive Aluno directory.
        $this->assertContains('students', $keys);
        $this->assertContains('dashboard', $keys);
        $this->assertContains('courses', $keys);
    }

    public function test_aluno_sees_no_administration_block_at_all(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $sections = $this->service->build($aluno);

        //  the entire Administração section must not be rendered.
        foreach ($sections as $section) {
            $this->assertNotSame('Administração', $section->title, 'Aluno must never see the Administração section.');
        }
    }

    public function test_the_forum_item_never_exists_in_the_navigation_for_any_role(): void
    {
        //  the forum is scoped to ONE course, so the
        // generalist sidebar entry was removed: the Aluno reaches it from
        // within the classroom, where the `{course}` context is
        // unambiguous. Even an ACTIVE enrollment cannot bring the item
        // back — the absence is structural, not enrollment-dependent.
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $keys = $this->keysFor($aluno);

        $this->assertNotContains('forum', $keys);
        $this->assertContains('student-courses', $keys);
    }

    // ──  "Meus Cursos" course blocks ─────────────────────────

    /**
     *  every ACTIVE (or completed, below) enrollment becomes an
     * always-visible block under "Meus Cursos": alphabetical by course
     * title (not enrollment order), each carrying its
     * `course_user.progress_percentage`, lesson counts, forum URL and —
     * when issued — the certificate URL. The fixed "Ver todos os
     * cursos" child is ALWAYS appended now: with the parent anchor
     * gone, it is the only menu path to `student.courses.index`.
     */
    public function test_aluno_with_active_enrollments_gets_alphabetical_course_blocks(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $zulu = Course::factory()->for($org)->create(['title' => 'Zulu']);
        $alfa = Course::factory()->for($org)->create(['title' => 'Alfa']);
        $aluno->courses()->attach($zulu->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 40]);
        $aluno->courses()->attach($alfa->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 0]);

        $children = $this->findItem($aluno, 'student-courses')['children'];

        $this->assertSame(['Alfa', 'Zulu', 'Ver todos os cursos'], array_column($children, 'label'));
        $this->assertSame('course-'.$alfa->id, $children[0]['key']);
        $this->assertSame(route('classroom.show', $alfa), $children[0]['url']);
        $this->assertSame(0, $children[0]['progress']);
        $this->assertSame(40, $children[1]['progress']);
        //  the escape hatch to the full catalog is persistent —
        // it closes the list for every Aluno, truncated or not.
        $this->assertSame('see-all', $children[2]['key']);
        $this->assertSame(route('student.courses.index'), $children[2]['url']);
        $this->assertFalse($children[2]['is_course']);
        //  no route is dispatched in a unit test, so no child can
        // claim the active highlight here (covered against a bound
        // `{course}` route below).
        $this->assertFalse($children[0]['active']);
    }

    /**
     *  the rich block payload: forum link always (any enrollment the
     * middleware accepts reaches `forum.index`), certificate ONLY when
     * issued and NOT revoked, lesson counts from the classroom universe
     * (published lessons of non-deleted modules — an unpublished lesson
     * counts in NEITHER number).
     */
    public function test_course_blocks_carry_counts_forum_and_certificate_payload(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $certified = Course::factory()->for($org)->create(['title' => 'A Certificado']);
        $revoked = Course::factory()->for($org)->create(['title' => 'B Revogado']);
        $uncertified = Course::factory()->for($org)->create(['title' => 'C Sem Certificado']);

        $moduleCertified = Module::factory()->for($certified)->create();
        $done = Lesson::factory()->for($moduleCertified)->create(['is_published' => true]);
        Lesson::factory()->for($moduleCertified)->create(['is_published' => true]);
        Lesson::factory()->for($moduleCertified)->create(['is_published' => false]);
        LessonProgress::query()->create([
            'user_id' => $aluno->id,
            'lesson_id' => $done->id,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        foreach ([$certified, $revoked, $uncertified] as $course) {
            $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        }

        Certificate::factory()->create(['user_id' => $aluno->id, 'course_id' => $certified->id]);
        Certificate::factory()->revoked()->create(['user_id' => $aluno->id, 'course_id' => $revoked->id]);

        $children = collect($this->findItem($aluno, 'student-courses')['children'])
            ->filter(fn ($child) => $child['is_course'])
            ->values()
            ->all();

        $this->assertSame(
            [$certified->id, $revoked->id, $uncertified->id],
            array_column($children, 'course_id'),
        );

        [$certifiedChild, $revokedChild, $uncertifiedChild] = $children;

        // 1. Counts follow the classroom universe (2 published, 1 done;
        //    the unpublished lesson stays out of the denominator).
        $this->assertSame(1, $certifiedChild['lessons_completed']);
        $this->assertSame(2, $certifiedChild['lessons_total']);

        // 2. Forum: every enrollment block carries its own forum URL.
        $this->assertSame(route('forum.index', $certified), $certifiedChild['forum_url']);
        $this->assertSame(route('forum.index', $uncertified), $uncertifiedChild['forum_url']);

        // 3. Certificate: issued-and-live only; revoked and never-issued
        //    both render NO line at all.
        $this->assertSame(route('certificates.download', ['certificate' => Certificate::query()->where('course_id', $certified->id)->firstOrFail()->id]), $certifiedChild['certificate_url']);
        $this->assertNull($revokedChild['certificate_url']);
        $this->assertNull($uncertifiedChild['certificate_url']);
    }

    public function test_aluno_without_enrollments_gets_only_the_ver_todos_child(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $children = $this->findItem($aluno, 'student-courses')['children'];

        //  the persistent "Ver todos os cursos" child keeps the
        // zero-enrollment Aluno a menu path to `/meus-cursos` (the old
        // parent anchor's job).
        $this->assertSame(['see-all'], array_column($children, 'key'));
    }

    /**
     *  completed enrollments ARE blocks now — the certificate is
     * issued exactly when the pivot flips to `completed`, so excluding
     * them would make the certificate line unreachable from the menu.
     * `cancelled` stays out (same pivot rule as
     * `EnsureStudentIsEnrolled`).
     */
    public function test_completed_enrollments_render_blocks_and_cancelled_ones_never_do(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $completed = Course::factory()->for($org)->create(['title' => 'A Concluído']);
        $cancelled = Course::factory()->for($org)->create(['title' => 'B Cancelado']);
        $aluno->courses()->attach($completed->id, ['status' => 'completed', 'enrolled_at' => now()]);
        $aluno->courses()->attach($cancelled->id, ['status' => 'cancelled', 'enrolled_at' => now()]);

        $children = $this->findItem($aluno, 'student-courses')['children'];

        $this->assertSame(
            [$completed->id, null],
            array_column($children, 'course_id'),
            'The completed enrollment renders as a block; cancelled never does.',
        );
        $this->assertSame('A Concluído', $children[0]['label']);
        $this->assertSame('Ver todos os cursos', $children[1]['label']);
    }

    /**
     *  the menu must not bloat for a heavily-enrolled Aluno: the
     * alphabetical cap keeps the first ten titles and hands the rest to
     * the fixed "Ver todos os cursos" child.
     */
    public function test_course_children_are_capped_at_ten_plus_ver_todos(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        for ($i = 12; $i >= 1; $i--) {
            $course = Course::factory()->for($org)->create(['title' => sprintf('Curso %02d', $i)]);
            $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        }

        $children = $this->findItem($aluno, 'student-courses')['children'];
        $labels = array_column($children, 'label');

        $this->assertCount(11, $children, 'Children must be capped at 10 courses + "Ver todos os cursos".');
        $this->assertSame('Curso 01', $labels[0]);
        $this->assertSame('Curso 10', $labels[9]);
        $this->assertSame('Ver todos os cursos', $labels[10]);
        $this->assertNotContains('Curso 11', $labels);
        $this->assertNotContains('Curso 12', $labels);
    }

    /**
     * /security — the pivot is the enrollment boundary, but a course
     * the Aluno never enrolled in must never appear, even when it
     * belongs to a visible-and-published catalog of another Organization.
     */
    public function test_children_never_leak_unenrolled_courses_of_another_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $ownCourse = Course::factory()->for($orgA)->create(['title' => 'Meu Curso']);
        Course::factory()->for($orgB)->create(['title' => 'Curso Alheio']);

        $aluno = User::factory()->create(['org_id' => $orgA->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($ownCourse->id, ['status' => 'active', 'enrolled_at' => now()]);

        $children = $this->findItem($aluno, 'student-courses')['children'];
        $labels = array_column($children, 'label');

        $this->assertSame(['Meu Curso', 'Ver todos os cursos'], $labels);
    }

    /**
     *  a child is highlighted only when the dispatched route resolves
     * to the *same* course. The classroom/lesson/quiz/forum routes type
     * their `{course}` params as `int` (no implicit binding), so the raw
     * param stays a scalar — the source of truth is the request attribute
     * set by `EnsureStudentIsEnrolled`, which the sidebar reads.
     */
    public function test_child_is_active_when_the_request_resolves_the_same_course(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $alfa = Course::factory()->for($org)->create(['title' => 'Alfa']);
        $zulu = Course::factory()->for($org)->create(['title' => 'Zulu']);
        $aluno->courses()->attach($alfa->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($zulu->id, ['status' => 'active', 'enrolled_at' => now()]);

        $route = (new Route('GET', 'courses/{course}/classroom', []))
            ->name('classroom.show');
        //  raw scalar, exactly as the framework delivers it on
        // classroom routes (controllers type-hint `int`).
        $route->parameters = ['course' => $alfa->id];

        $request = Request::create("/courses/{$alfa->id}/classroom", 'GET');
        $request->setRouteResolver(fn () => $route);
        $request->attributes->set(EnsureStudentIsEnrolled::RESOLVED_COURSE_ATTRIBUTE, $alfa);

        $service = new NavigationService(new NavigationRegistry, $request);

        $children = collect($service->build($aluno))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'student-courses')['children'];

        $this->assertTrue($children[0]['active'], 'The child of the resolved course must be highlighted.');
        $this->assertFalse($children[1]['active'], 'Unrelated course children must not be highlighted.');
    }

    /**
     *  fallback path — when the middleware attribute is absent (a
     * route that binds `Course` implicitly), the raw bound model still
     * matches the child.
     */
    public function test_child_is_active_falls_back_to_the_bound_course_parameter(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $alfa = Course::factory()->for($org)->create(['title' => 'Alfa']);
        $aluno->courses()->attach($alfa->id, ['status' => 'active', 'enrolled_at' => now()]);

        $route = (new Route('GET', 'courses/{course}/classroom', []))
            ->name('classroom.show');
        $route->parameters = ['course' => $alfa];

        $request = Request::create("/courses/{$alfa->id}/classroom", 'GET');
        $request->setRouteResolver(fn () => $route);

        $service = new NavigationService(new NavigationRegistry, $request);

        $children = collect($service->build($aluno))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'student-courses')['children'];

        $this->assertTrue($children[0]['active']);
    }

    /**
     *  the course block also highlights on the course FORUM — the
     * forum routes run the same `student.enrolled` middleware, which
     * exposes the resolved `Course` attribute the sidebar reads.
     */
    public function test_child_is_active_on_the_course_forum_route_too(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $alfa = Course::factory()->for($org)->create(['title' => 'Alfa']);
        $zulu = Course::factory()->for($org)->create(['title' => 'Zulu']);
        $aluno->courses()->attach($alfa->id, ['status' => 'active', 'enrolled_at' => now()]);
        $aluno->courses()->attach($zulu->id, ['status' => 'active', 'enrolled_at' => now()]);

        $route = (new Route('GET', 'courses/{course}/forum', []))
            ->name('forum.index');
        $route->parameters = ['course' => $alfa->id];

        $request = Request::create("/courses/{$alfa->id}/forum", 'GET');
        $request->setRouteResolver(fn () => $route);
        $request->attributes->set(EnsureStudentIsEnrolled::RESOLVED_COURSE_ATTRIBUTE, $alfa);

        $service = new NavigationService(new NavigationRegistry, $request);

        $children = collect($service->build($aluno))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'student-courses')['children'];

        $this->assertTrue($children[0]['active'], 'The block of the forum course must be highlighted.');
        $this->assertFalse($children[1]['active'], 'Unrelated course blocks must not be highlighted.');
    }

    /**
     *  exactly at the cap there is no truncation to announce, but the
     * persistent "Ver todos os cursos" child still closes the list.
     */
    public function test_exactly_ten_courses_render_ten_blocks_plus_ver_todos(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        for ($i = 10; $i >= 1; $i--) {
            $course = Course::factory()->for($org)->create(['title' => sprintf('Curso %02d', $i)]);
            $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);
        }

        $children = $this->findItem($aluno, 'student-courses')['children'];

        $this->assertCount(11, $children);
        $this->assertSame('Curso 01', $children[0]['label']);
        $this->assertSame('Curso 10', $children[9]['label']);
        $this->assertSame('Ver todos os cursos', $children[10]['label']);
    }

    public function test_admin_audit_logs_route_resolves_to_admin_prefixed_route(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $auditItem = $this->findItem($admin, 'audit-logs');

        $this->assertSame(route('admin.audit-logs.index'), $auditItem['url']);
    }

    public function test_gestor_has_no_audit_logs_item_at_all(): void
    {
        //  audit is a system-administration surface:
        // `roles: ['admin']` on the item mirrors the route's
        // `role:admin` middleware (the legacy Gestor-prefixed routes were
        // removed), so the item is filtered out for a Gestor entirely.
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $keys = $this->keysFor($gestor);

        $this->assertNotContains('audit-logs', $keys);
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
        // /security — `ForumReport` has no `OrgScope`; the badge must
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
        // The `students` item is the Gestor-exclusive people-management
        // entry ( `users` is Admin-only now).
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        // `routeIs()` reads `request()->route()->named(...)`. In a unit
        // test no route is dispatched, so a real `Illuminate\Routing\Route`
        // is bound to the request via `setRouteResolver()` — exercising
        // the active wildcard matching  against the framework's own
        // `named()` implementation, not a re-implementation of it. The
        // Feature/Dusk suites additionally cover dispatched-route parity.
        $route = (new Route('GET', 'gestor/students/{user}/edit', []))
            ->name('gestor.students.edit');

        $request = Request::create('/gestor/students/1/edit', 'GET');
        $request->setRouteResolver(fn () => $route);

        $service = new NavigationService(new NavigationRegistry, $request);

        $studentsItem = collect($service->build($gestor))
            ->flatMap(fn ($section) => $section->items)
            ->firstWhere('key', 'students');

        //  the `gestor.students.*` wildcard highlights the
        // parent on a sub-route like `gestor.students.edit`.
        $this->assertTrue($studentsItem['active']);
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
     * @return array{key: string, label: string, url: string, active: bool, badge: int|string|null, icon: string, section: string, childrenOnly: bool, children: list<array{key: string, label: string, url: string, active: bool, progress: int|null, is_course: bool, lessons_completed: int|null, lessons_total: int|null, forum_url: string|null, certificate_url: string|null}>}
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
