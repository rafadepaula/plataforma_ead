<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-11 (RF12/RN05) — E2E coverage of the contextual Help Center: an
 * Aluno clicks the help button on their "Meus Cursos" screen, the modal
 * opens, and the resolved `HelpArticle`'s content is shown.
 */
class HelpCenterDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_student_opens_the_help_button_and_sees_the_article_content(): void
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        HelpArticle::factory()->global()->create([
            'target_page_key' => 'student.courses.index',
            'title' => 'Como acessar meus cursos',
            'content' => 'Aqui você encontra todos os cursos em que está matriculado.',
        ]);

        $this->browse(function (Browser $browser) use ($student): void {
            $browser->loginAs($student)
                ->visit(route('student.courses.index'))
                ->waitFor('@help-button-student.courses.index')
                // `x-ui.modal`'s backdrop ships with a static inline
                // `display: flex` and is only hidden once ModalManager's
                // `hideBackdropsOnLoad()` runs on `DOMContentLoaded` (this
                // project has no Alpine.js — see ModalManager.js's
                // docblock). Waits for that plain-JS hide before clicking
                // the trigger button — otherwise, on a fast page load, the
                // still-visible fixed-position backdrop can intercept the
                // click meant for the button beneath it.
                ->waitUntilMissing('.dialog-backdrop')
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-article-content-student.courses.index')
                ->assertSee('Como acessar meus cursos')
                ->assertSee('Aqui você encontra todos os cursos em que está matriculado.');
        });
    }

    public function test_the_help_button_falls_back_to_the_global_article(): void
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        HelpArticle::factory()->global()->create([
            'target_page_key' => 'student.courses.index',
            'title' => 'Artigo global de fallback',
            'content' => 'Conteúdo global exibido quando não há artigo específico da organização.',
        ]);

        $this->browse(function (Browser $browser) use ($student): void {
            $browser->loginAs($student)
                ->visit(route('student.courses.index'))
                ->waitFor('@help-button-student.courses.index')
                // See the docblock above for why this wait is required
                // before clicking the trigger button.
                ->waitUntilMissing('.dialog-backdrop')
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-article-content-student.courses.index')
                ->assertSeeIn('.dialog-title', 'Artigo global de fallback')
                ->assertSeeIn('@help-article-content-student.courses.index', 'Conteúdo global exibido quando não há artigo específico da organização.');
        });
    }
}
