<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UC13 (SPEC-09 §1.1) — E2E coverage of the Gestor's completion-rule CRUD
 * screen (`courses.completion-rules.*`): creating an `all_lessons` rule via
 * the UI and seeing it appear in the table, then removing it.
 */
class CourseCompletionRuleTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_creates_a_completion_rule_via_the_ui(): void
    {
        $gestor = User::factory()->gestor()->create();
        $course = Course::factory()->create(['org_id' => $gestor->org_id, 'title' => 'Curso Regras Dusk']);

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.completion-rules.index', $course))
                ->waitFor('@completion-rule-form')
                ->select('@completion-rule-type', 'all_lessons')
                ->clear('required_percentage')
                ->type('required_percentage', '80')
                ->click('@completion-rule-submit')
                ->waitForText('Regra de conclusão criada com sucesso.')
                ->assertSee('80%');
        });

        $rule = CourseCompletionRule::query()->where('course_id', $course->id)->first();

        $this->assertNotNull($rule);
        $this->assertSame('all_lessons', $rule->rule_type);
        $this->assertSame(80, $rule->required_percentage);

        $this->browse(function (Browser $browser) use ($gestor, $course, $rule): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.completion-rules.index', $course))
                ->waitFor('@completion-rule-row-'.$rule->id)
                ->assertSee('80%');
        });
    }

    public function test_gestor_removes_a_completion_rule_via_the_ui(): void
    {
        $gestor = User::factory()->gestor()->create();
        $course = Course::factory()->create(['org_id' => $gestor->org_id]);
        $rule = CourseCompletionRule::factory()->allLessons()->for($course)->create();

        $this->browse(function (Browser $browser) use ($gestor, $course, $rule): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.completion-rules.index', $course))
                ->waitFor('@completion-rule-row-'.$rule->id)
                ->click('@delete-completion-rule-'.$rule->id)
                ->waitForText('Regra de conclusão removida com sucesso.')
                ->assertMissing('@completion-rule-row-'.$rule->id);
        });

        $this->assertDatabaseMissing('course_completion_rules', ['id' => $rule->id]);
    }
}
