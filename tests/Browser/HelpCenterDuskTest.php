<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\HelpArticle;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-11 (RF12/RN05) — E2E coverage of the contextual Help Center.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada do botão de ajuda na tela "Meus Cursos" — sem artigo (placeholder)
 * → com artigo global publicado (conteúdo resolvido pelo fallback) — é um
 * único método, pois é o mesmo ator na mesma tela mudando de estado.
 */
class HelpCenterDuskTest extends DuskTestCase
{
    public function test_help_button_placeholder_then_resolved_article_lifecycle(): void
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($student): void {
            // 1. Sem `HelpArticle` semeado: o botão continua ativo e abre um
            //    modal de placeholder (nunca renderiza inerte).
            //
            //    `x-ui.modal` is plain Bootstrap markup: the modal is
            //    `display: none` until `data-bs-toggle="modal"` shows it, and
            //    the backdrop is created by `bootstrap.Modal` only on open —
            //    there is no pre-click backdrop to wait out.
            $browser->loginAs($student)
                ->visit(route('student.courses.index'))
                ->waitFor('@help-button-student.courses.index')
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-placeholder-content-student.courses.index')
                ->assertSeeIn('@help-modal-student.courses.index .modal-title', 'Ajuda')
                ->assertSeeIn('@help-placeholder-content-student.courses.index', 'Estamos preparando');

            // 2. Publicado um artigo GLOBAL (`org_id` nulo) para a chave da
            //    tela, o mesmo botão passa a resolver o conteúdo pelo
            //    fallback global.
            HelpArticle::factory()->global()->create([
                'target_page_key' => 'student.courses.index',
                'title' => 'Como acessar meus cursos',
                'content' => 'Aqui você encontra todos os cursos em que está matriculado.',
            ]);

            $this->assertDatabaseHas('help_articles', [
                'target_page_key' => 'student.courses.index',
                'org_id' => null,
            ]);

            $browser->visit(route('student.courses.index'))
                ->waitFor('@help-button-student.courses.index')
                ->click('@help-button-student.courses.index')
                ->waitFor('@help-article-content-student.courses.index')
                ->assertSeeIn('@help-modal-student.courses.index .modal-title', 'Como acessar meus cursos')
                ->assertSeeIn(
                    '@help-article-content-student.courses.index',
                    'Aqui você encontra todos os cursos em que está matriculado.'
                )
                ->assertMissing('@help-placeholder-content-student.courses.index');
        });
    }
}
