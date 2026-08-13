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
                // `x-ui.modal` is now plain Bootstrap markup: the modal is
                // `display: none` until `data-bs-toggle="modal"` shows it,
                // and the backdrop is created by `bootstrap.Modal` only on
                // open. There is therefore no pre-click backdrop to wait
                // out anymore (the old `.dialog-backdrop`/ModalManager
                // contract is gone from the project).
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
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-article-content-student.courses.index')
                ->assertSeeIn('@help-modal-student.courses.index .modal-title', 'Artigo global de fallback')
                ->assertSeeIn('@help-article-content-student.courses.index', 'Conteúdo global exibido quando não há artigo específico da organização.');
        });
    }

    public function test_the_help_button_shows_a_placeholder_when_no_article_exists(): void
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        // No `HelpArticle` seeded for `student.courses.index` — the
        // button must still be active and open a placeholder modal
        // instead of rendering disabled/inert.
        $this->browse(function (Browser $browser) use ($student): void {
            $browser->loginAs($student)
                ->visit(route('student.courses.index'))
                ->waitFor('@help-button-student.courses.index')
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-placeholder-content-student.courses.index')
                ->assertSeeIn('@help-modal-student.courses.index .modal-title', 'Ajuda')
                ->assertSeeIn('@help-placeholder-content-student.courses.index', 'Estamos preparando');
        });
    }
}
