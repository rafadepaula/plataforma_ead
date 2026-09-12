<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 *  the Modules index screen (courses/modules/index) renders each
 * module's lessons INLINE as sub-items — ordered by `order_index`, eager
 * loaded (no N+1) — and offers a direct "Cadastrar lição" entry into the
 * lesson create form for that module. The full lessons management screen
 * (`modules.lessons.index`) stays reachable via the sub-list "Gerenciar
 * lições" link (the historical `manage-lessons-{id}` dusk selector).
 */
class ModulesIndexLessonsSubitemsTest extends TestCase
{
    private Organization $org;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->actingAsOrgUser($this->org, RolesEnum::GESTOR->value);
        $this->course = Course::factory()->inOrg($this->org->id)->create();
    }

    private function getIndexResponse(): TestResponse
    {
        return $this->get(route('courses.modules.index', $this->course))->assertOk();
    }

    public function test_index_renders_each_lessons_sub_items_in_order_index_sequence(): void
    {
        $module = Module::factory()->for($this->course)->create();
        $second = Lesson::factory()->for($module)->richText()->create(['title' => 'Segunda Lição', 'order_index' => 1]);
        $first = Lesson::factory()->for($module)->richText()->create(['title' => 'Primeira Lição', 'order_index' => 0]);
        $unrelatedCourse = Course::factory()->inOrg($this->org->id)->create();
        $unrelatedModule = Module::factory()->for($unrelatedCourse)->create();
        $unrelated = Lesson::factory()->for($unrelatedModule)->richText()->create(['title' => 'Lição de Outro Módulo', 'order_index' => 0]);

        $html = $this->getIndexResponse()->getContent();

        // Ordered by order_index regardless of insertion order.
        $this->assertLessThan(
            strpos($html, 'dusk="lesson-subitem-'.$second->id.'"'),
            strpos($html, 'dusk="lesson-subitem-'.$first->id.'"'),
            'Lessons must render ordered by order_index.'
        );
        $this->assertStringContainsString('Primeira Lição', $html);
        $this->assertStringNotContainsString('Lição de Outro Módulo', $html);
    }

    public function test_sub_items_carry_type_badge_and_unpublished_state(): void
    {
        $module = Module::factory()->for($this->course)->create();
        $quiz = Lesson::factory()->for($module)->create(['title' => 'Avaliação Final', 'type' => 'quiz', 'order_index' => 0, 'is_published' => true]);
        $draft = Lesson::factory()->for($module)->richText()->create(['title' => 'Rascunho', 'order_index' => 1, 'is_published' => false]);

        $html = $this->getIndexResponse()->getContent();

        $quizRow = substr($html, (int) strpos($html, 'dusk="lesson-subitem-'.$quiz->id.'"'), 2000);
        $draftRow = substr($html, (int) strpos($html, 'dusk="lesson-subitem-'.$draft->id.'"'), 2000);

        $this->assertStringContainsString('>Quiz</span>', $quizRow);
        $this->assertStringContainsString('>Conteúdo</span>', $draftRow);
        $this->assertStringContainsString('Não publicada', $draftRow);
    }

    public function test_index_eager_loads_lessons_without_n_plus_one(): void
    {
        $moduleA = Module::factory()->for($this->course)->create();
        $moduleB = Module::factory()->for($this->course)->create();
        Lesson::factory()->count(3)->for($moduleA)->richText()->create();
        Lesson::factory()->count(3)->for($moduleB)->richText()->create();

        DB::enableQueryLog();
        $this->getIndexResponse();
        $baseline = count(DB::getQueryLog());
        DB::flushQueryLog();

        // Doubling the rendered lessons must not add a single query —
        // that is exactly the N+1 the eager load exists to prevent.
        Lesson::factory()->count(6)->for($moduleA)->richText()->create();
        Lesson::factory()->count(6)->for($moduleB)->richText()->create();
        DB::flushQueryLog();
        $this->getIndexResponse();
        $doubled = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            $baseline,
            $doubled,
            'Query count must not grow with the number of rendered lessons.'
        );
    }

    public function test_row_offers_cadastrar_licao_button_pointing_to_the_lesson_create_form(): void
    {
        $module = Module::factory()->for($this->course)->create();

        $html = $this->getIndexResponse()->getContent();

        $this->assertStringContainsString('dusk="create-lesson-'.$module->id.'"', $html);
        $this->assertStringContainsString('Cadastrar lição', $html);
        $this->assertStringContainsString(
            'href="'.route('modules.lessons.create', $module).'"',
            $html,
            'The button must link straight to the lesson create form with the module preselected by URL.'
        );
        $this->assertStringNotContainsString('>Lições</', $html, 'The old two-step "Lições" button must be gone.');
    }

    public function test_lessons_create_form_receives_the_preselected_module(): void
    {
        $module = Module::factory()->for($this->course)->create(['title' => 'Módulo Pré-selecionado']);

        $this->get(route('modules.lessons.create', $module))
            ->assertOk()
            ->assertViewIs('modules.lessons.create')
            ->assertViewHas('module', fn (Module $viewModule) => $viewModule->is($module))
            ->assertSee('Módulo Pré-selecionado');
    }

    public function test_sub_list_keeps_the_full_lessons_index_reachable(): void
    {
        $module = Module::factory()->for($this->course)->create();

        $html = $this->getIndexResponse()->getContent();

        $this->assertStringContainsString('dusk="manage-lessons-'.$module->id.'"', $html);
        $this->assertStringContainsString('href="'.route('modules.lessons.index', $module).'"', $html);

        $this->get(route('modules.lessons.index', $module))
            ->assertOk()
            ->assertViewIs('modules.lessons.index');
    }

    /**
     * A sub-lista de lições é estática (sempre expandida): o chevron de
     * collapse (`module-lessons-toggle-{id}`) foi removido, então não pode
     * restar markup de toggle nem a classe `collapse` no bloco. Os botões
     * mover para cima/baixo da linha expõem tooltip Bootstrap + aria-label.
     */
    public function test_lesson_sub_list_is_always_expanded_and_move_buttons_carry_tooltips(): void
    {
        $module = Module::factory()->for($this->course)->create();

        $html = $this->getIndexResponse()->getContent();

        // Toggle removido: sem markup de collapse, o bloco é sempre visível.
        $this->assertStringNotContainsString('module-lessons-toggle-', $html);
        $subList = substr($html, (int) strpos($html, 'dusk="module-lessons-'.$module->id.'"') - 200, 400);
        $this->assertStringNotContainsString('class="collapse', $subList, 'The lesson sub-list must render statically, without Bootstrap Collapse.');

        // Tooltips de acessibilidade nos botões de reordenação.
        $this->assertStringContainsString('data-bs-title="Mover para cima"', $html);
        $this->assertStringContainsString('data-bs-title="Mover para baixo"', $html);
        $this->assertStringContainsString('aria-label="Mover '.e($module->title).' para cima"', $html);
        $this->assertStringContainsString('aria-label="Mover '.e($module->title).' para baixo"', $html);
    }

    public function test_lesson_sub_items_expose_the_edit_form_target(): void
    {
        $module = Module::factory()->for($this->course)->create();
        $lesson = Lesson::factory()->for($module)->richText()->create();

        $html = $this->getIndexResponse()->getContent();

        $this->assertStringContainsString('dusk="lesson-subitem-'.$lesson->id.'"', $html);
        $this->assertStringContainsString(
            'href="'.route('lessons.edit', $lesson).'"',
            $html,
            'Each sub-item must link to the lesson edit form.'
        );
    }

    /**
     * O cabeçalho expõe o fórum de dúvidas a todo staff do curso — o
     * mesmo perímetro do `ModulePolicy::authorizeForCourse()` (que já
     * autorizou a tela) e do `EnsureStudentIsEnrolled` no fórum: Admin
     * sempre, Gestor da própria org e Professor atribuído.
     */
    public function test_index_header_offers_a_forum_entry_point_to_the_gestor(): void
    {
        $html = $this->getIndexResponse()->getContent();

        $this->assertStringContainsString('dusk="course-forum-link"', $html);
        $this->assertStringContainsString('href="'.route('forum.index', $this->course).'"', $html);

        $this->get(route('forum.index', $this->course))->assertOk();
    }

    public function test_index_header_offers_the_forum_entry_point_to_the_admin(): void
    {
        $admin = User::factory()->inOrg(null)->create();
        $admin->assignRole(RolesEnum::ADMIN->value);

        $html = $this->actingAs($admin)
            ->withSession(['active_org_id' => $this->org->id])
            ->get(route('courses.modules.index', $this->course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dusk="course-forum-link"', $html);

        $this->actingAs($admin)
            ->withSession(['active_org_id' => $this->org->id])
            ->get(route('forum.index', $this->course))
            ->assertOk();
    }

    public function test_index_header_offers_the_forum_entry_point_to_the_assigned_professor(): void
    {
        $gestor = $this->actingAsOrgUser($this->org);
        $professor = User::factory()->professor()->inOrg($this->org->id)->create();
        $this->course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $html = $this->actingAs($professor)
            ->get(route('courses.modules.index', $this->course))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('dusk="course-forum-link"', $html);
        $this->assertStringContainsString('href="'.route('forum.index', $this->course).'"', $html);

        // O docente atribuído carrega a página do fórum (middleware
        // `student.enrolled` aceita `User::teaches()`), sem matrícula.
        $this->actingAs($professor)
            ->get(route('forum.index', $this->course))
            ->assertOk();
    }

    public function test_forum_entry_is_never_offered_to_non_staff_viewers(): void
    {
        // Professor não atribuído ao curso: nem a tela de módulos ele alcança.
        $outsider = User::factory()->professor()->inOrg($this->org->id)->create();
        $this->actingAs($outsider)->get(route('courses.modules.index', $this->course))->assertForbidden();

        // Aluno ativo, mesmo com matrícula válida, continua fora da gestão.
        $student = User::factory()->inOrg($this->org->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $this->actingAs($student)->get(route('courses.modules.index', $this->course))->assertForbidden();
    }
}
