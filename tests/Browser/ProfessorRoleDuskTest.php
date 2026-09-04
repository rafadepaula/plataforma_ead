<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage do papel `professor` (happy path).
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada do próprio Professor (entrar pelo formulário → dashboard de
 * Ensino → seção do menu → "Meus Cursos" vazio → curso atribuído aparece)
 * é um método; a jornada de correção manual (fila → tela de correção →
 * veredito → tentativa sai da fila) é outro; a atribuição feita pelo
 * Gestor no painel do curso fica isolada porque é outro ator.
 *
 * O perímetro do Professor é a pivot `course_professor` (`User::teaches()`),
 * nunca a Organização: é ela que é exercitada nos três métodos.
 *
 * KNOWN BLOCKERS (2026-09-04): `test_professor_teaching_menu_and_meus_cursos_lifecycle`
 * e `test_professor_essay_grading_lifecycle` falham com 500 enquanto dois
 * bugs de query do papel `professor` não forem corrigidos — ambos
 * invisíveis para a suíte Feature, que roda em SQLite:
 *
 *  1. `ProfessorDashboardService::quickAccessCourses()` (linha 99) e
 *     `ProfessorCourseController::index()` (linha 34) chamam
 *     `wherePivot('course_user.status', 'active')` DENTRO de um closure de
 *     `withCount()` — a coluna pivot se qualifica errada e compila como
 *     `where "pivot" = course_user.status`. MySQL: SQLSTATE 42S22 (500 em
 *     `/professor/dashboard` e `/professor/courses`); SQLite: a constante
 *     `"pivot"` vale como string literal e `students_count` volta
 *     silenciosamente 0. Idioma correto já usado em
 *     `CourseController::index()`: `$query->where('course_user.status', 'active')`.
 *
 *  2. `EssayGradingController::pending()` (linha 47) passa um
 *     `BelongsToMany` para `whereKey()`:
 *     `$user->taughtCourses()->select('courses.id')` →
 *     `TypeError: MySqlGrammar::compileSelect(): Argument #1 ($query) must
 *     be of type Query\Builder, BelongsToMany given` (500 em
 *     `/quiz-attempts/pending`, qualquer Professor). Já coberto por
 *     `ProfessorEssayGradingTest`. Correção: `->select('courses.id')->toBase()`.
 */
class ProfessorRoleDuskTest extends DuskTestCase
{
    public function test_professor_teaching_menu_and_meus_cursos_lifecycle(): void
    {
        $org = Organization::factory()->create();

        $professor = User::factory()->professor()->create([
            'org_id' => $org->id,
            'email' => 'professor.dusk@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso de Eletricista']);

        $this->browse(function (Browser $browser) use ($professor, $course): void {
            // 1. Login pelo formulário: o Professor cai no dashboard de Ensino,
            //    não no dashboard administrativo.
            $browser->visit('/login')
                ->assertPresent('@login-form')
                ->type('@login-email', 'professor.dusk@example.com')
                ->type('@login-password', 'correct-password')
                ->press('@login-submit')
                ->waitForLocation('/professor/dashboard')
                ->waitFor('@professor-dashboard')
                ->assertAuthenticatedAs($professor)
                ->assertSee('Dashboard');

            // 2. Seção "Ensino" no menu: os 4 itens do papel e nenhum item
            //    de Administração (Cursos/Usuários/Organizações/Professores).
            //    (Macro do projeto `assertSeeIgnoringCase`: o Selenium lê
            //    texto RENDERIZADO e o `.sidebar-section-title` usa
            //    `text-transform: uppercase` — "ENSINO" no getText.)
            $browser->assertSeeIgnoringCase('Ensino')
                ->assertPresent('@sidebar-professor-dashboard-link')
                ->assertPresent('@sidebar-professor-courses-link')
                ->assertPresent('@sidebar-professor-grading-link')
                ->assertPresent('@sidebar-professor-moderation-link')
                ->assertMissing('@sidebar-courses-link')
                ->assertMissing('@sidebar-users-link')
                ->assertMissing('@sidebar-organizations-link')
                ->assertMissing('@sidebar-professors-link');

            // 3. Sem atribuição, "Meus Cursos" mostra o estado vazio.
            $browser->visit(route('professor.courses.index'))
                ->waitFor('@professor-courses-index')
                ->assertPresent('@professor-courses-empty')
                ->assertMissing('@professor-course-card-'.$course->id);

            // 4. A pivot `course_professor` é o único filtro: atribuir pelo
            //    banco (sem nova sessão) faz o card aparecer na próxima visita.
            $course->professors()->syncWithoutDetaching([$professor->id]);

            $browser->visit(route('professor.courses.index'))
                ->waitFor('@professor-course-card-'.$course->id)
                ->assertSeeIn('@professor-course-card-'.$course->id, 'Curso de Eletricista')
                ->assertMissing('@professor-courses-empty');
        });

        $this->assertDatabaseHas('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
        ]);
    }

    public function test_professor_essay_grading_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        /** @var User $aluno */
        $aluno = User::factory()->create(['org_id' => null, 'name' => 'Aluno Dusk']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $professor = User::factory()->professor()->create(['org_id' => $org->id]);
        $course->professors()->syncWithoutDetaching([$professor->id]);

        $attempt = QuizAttempt::factory()->for($quiz)->for($aluno)->awaitingManualGrading()
            ->create(['completed_at' => now()->subMinutes(5)]);
        $answer = QuizAnswer::factory()->for($attempt, 'attempt')->for($essayQuestion, 'question')
            ->essay('A corrente elétrica é a razão entre a carga e o tempo.')->create();

        $this->browse(function (Browser $browser) use ($professor, $attempt, $answer, $essayQuestion): void {
            // 1. A fila de correções do Professor traz só as tentativas dos
            //    cursos a ele atribuídos.
            $browser->loginAs($professor)
                ->visit(route('quiz-attempts.pending'))
                ->waitFor('@pending-attempt-row-'.$attempt->id)
                ->assertSeeIn('@pending-attempt-row-'.$attempt->id, 'Aluno Dusk');

            // 2. "Corrigir" abre a tela de correção com a resposta dissertativa.
            $browser->click('@grade-attempt-'.$attempt->id)
                ->waitFor('@grade-attempt-form')
                ->assertPresent('@essay-answer-'.$essayQuestion->id)
                ->assertSeeIn('@essay-answer-'.$essayQuestion->id, 'A corrente elétrica é a razão entre a carga e o tempo.');

            // 3. Veredito + submit finaliza a tentativa e devolve à fila,
            //    que agora está vazia.
            $browser->click('@grade-correct-'.$answer->id)
                ->press('@grade-attempt-submit')
                ->waitForLocation('/quiz-attempts/pending')
                ->waitForText('Correção registrada com sucesso.')
                ->assertPresent('@pending-attempts-empty')
                ->assertMissing('@pending-attempt-row-'.$attempt->id);
        });

        $this->assertDatabaseHas('quiz_attempts', [
            'id' => $attempt->id,
            'status' => 'graded',
            'is_passed' => true,
        ]);

        $this->assertDatabaseHas('quiz_answers', [
            'id' => $answer->id,
            'is_correct' => true,
            'graded_by' => $professor->id,
        ]);
    }

    public function test_gestor_assigns_professor_to_course_lifecycle(): void
    {
        $org = Organization::factory()->create();

        $gestor = User::factory()->gestor()->create(['org_id' => $org->id]);

        $professor = User::factory()->professor()->create([
            'org_id' => $org->id,
            'name' => 'Prof. Dusk',
        ]);

        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso de Eletricista']);

        $this->browse(function (Browser $browser) use ($gestor, $professor, $course): void {
            // 1. O painel do curso lista o Professor da mesma Organização no
            //    pool "Professores disponíveis" e ninguém em "atribuídos".
            $browser->loginAs($gestor)
                ->visit(route('courses.professors.index', $course))
                ->waitFor('@course-professors-index')
                ->assertPresent('@available-professor-row-'.$professor->id)
                ->assertMissing('@course-professor-row-'.$professor->id);

            // 2. "Atribuir" grava a pivot e move o Professor para a lista de
            //    atribuídos, saindo do pool.
            $browser->press('@attach-professor-'.$professor->id)
                ->waitFor('@course-professor-row-'.$professor->id)
                ->assertSeeIn('@course-professor-row-'.$professor->id, 'Prof. Dusk')
                ->assertMissing('@available-professor-row-'.$professor->id);
        });

        $this->assertDatabaseHas('course_professor', [
            'course_id' => $course->id,
            'user_id' => $professor->id,
            'assigned_by' => $gestor->id,
        ]);
    }
}
