<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * UC13 (SPEC-09 §1.1) — E2E coverage of the Gestor's completion-rule CRUD
 * screen (`courses.completion-rules.*`).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): criar a
 * regra → vê-la na tabela após recarregar → removê-la é uma jornada única.
 */
class CourseCompletionRuleTest extends DuskTestCase
{
    public function test_gestor_completion_rule_crud_lifecycle(): void
    {
        $gestor = User::factory()->gestor()->create();
        $course = Course::factory()->create(['org_id' => $gestor->org_id, 'title' => 'Curso Regras Dusk']);

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            // 1. Criação da regra `all_lessons` com 80%.
            $browser->loginAs($gestor)
                ->visit(route('courses.completion-rules.index', $course))
                ->waitFor('@completion-rule-form')
                ->select('@completion-rule-type', 'all_lessons')
                ->clear('required_percentage')
                ->type('required_percentage', '80')
                ->click('@completion-rule-submit')
                ->waitForText('Regra de conclusão criada com sucesso.')
                ->assertSee('80%');

            $rule = CourseCompletionRule::query()->where('course_id', $course->id)->firstOrFail();
            $this->assertSame('all_lessons', $rule->rule_type);
            $this->assertSame(80, $rule->required_percentage);

            // 2. A regra sobrevive a um recarregamento da tela.
            $browser->visit(route('courses.completion-rules.index', $course))
                ->waitFor('@completion-rule-row-'.$rule->id)
                ->assertSee('80%');

            // 3. Remoção: some da tabela e do banco.
            $browser->click('@delete-completion-rule-'.$rule->id)
                ->waitForText('Regra de conclusão removida com sucesso.')
                ->assertMissing('@completion-rule-row-'.$rule->id);

            $this->assertDatabaseMissing('course_completion_rules', ['id' => $rule->id]);
        });

        $this->assertDatabaseCount('course_completion_rules', 0);
    }
}
