<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the "Meus Cursos" Material Bootstrap catalog:
 * segmented `?status=` tabs, the `x-course.card` grid (4 status chips,
 * progress bar, contextual CTA per status), and the per-tab empty state.
 *
 * Agrupado por cadeia de ciclo de vida (ver `laravel-dusk`/
 * `testing-conventions`): a jornada do aluno com matrículas nos 4 status
 * percorre as 3 abas num único login; o estado vazio (sem matrícula
 * nenhuma) é uma jornada independente, num segundo método.
 */
class StudentCoursesCatalogUiTest extends DuskTestCase
{
    public function test_student_courses_catalog_tabs_and_cards_lifecycle(): void
    {
        $org = Organization::factory()->create();

        // "Em andamento": a published lesson exists, the pivot has partial
        // progress, no `expires_at` — CTA is "Continuar" to the resume lesson.
        $inProgressCourse = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Em Andamento', 'workload_hours' => 4]);
        $inProgressModule = Module::factory()->for($inProgressCourse)->create(['order_index' => 0]);
        Lesson::factory()->for($inProgressModule)->create(['is_published' => true, 'order_index' => 0]);
        Lesson::factory()->for($inProgressModule)->create(['is_published' => true, 'order_index' => 1]);

        // "Concluído": pivot status completed, a Certificate already issued
        // — CTA is "Baixar certificado".
        $completedCourse = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Concluido', 'workload_hours' => 2]);
        $completedModule = Module::factory()->for($completedCourse)->create(['order_index' => 0]);
        Lesson::factory()->for($completedModule)->create(['is_published' => true, 'order_index' => 0]);

        // "Expirado": pivot status active, `expires_at` in the past — CTA is
        // the read-only "Ver o que você fez" classroom link.
        $expiredCourse = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Expirado', 'workload_hours' => 3]);
        $expiredModule = Module::factory()->for($expiredCourse)->create(['order_index' => 0]);
        Lesson::factory()->for($expiredModule)->create(['is_published' => true, 'order_index' => 0]);

        /** @var User $student */
        $student = User::factory()->create([
            'org_id' => null,
            'email' => 'catalogo@example.com',
            'password' => Hash::make('senha-correta'),
        ]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $student->courses()->attach($inProgressCourse->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 30,
        ]);
        $student->courses()->attach($completedCourse->id, [
            'status' => 'completed',
            'enrolled_at' => now()->subDays(10),
            'completed_at' => now()->subDay(),
            'progress_percentage' => 100,
        ]);
        $student->courses()->attach($expiredCourse->id, [
            'status' => 'active',
            'enrolled_at' => now()->subDays(30),
            'progress_percentage' => 10,
            'expires_at' => now()->subDay(),
        ]);

        Certificate::factory()->for($completedCourse)->create([
            'user_id' => $student->id,
            'issued_at' => now()->subDay(),
        ]);

        $this->browse(function (Browser $browser) use ($student, $inProgressCourse, $completedCourse, $expiredCourse): void {
            // 1. Default landing is "Em andamento": only the in-progress
            //    course shows, with its "Em andamento" chip and progress bar.
            $browser->loginAs($student)
                ->visit('/meus-cursos')
                ->waitFor('@tab-em-andamento')
                ->assertAttribute('@tab-em-andamento', 'aria-selected', 'true')
                ->waitFor('@course-card-'.$inProgressCourse->id)
                // "Em andamento" maps to every `active` pivot row — the
                // expired enrollment is still `active` (its "expirado" chip
                // is a derived read of an active row past `expires_at`, not
                // a 4th pivot status), so it shows here too; only the
                // `completed` pivot is excluded.
                ->assertMissing('@course-card-'.$completedCourse->id)
                ->assertVisible('@course-card-'.$expiredCourse->id)
                ->assertPresent('@course-progress-'.$inProgressCourse->id)
                ->assertVisible('@course-continue-'.$inProgressCourse->id);

            // 2. "Concluídos" tab: a plain GET reload (no client-side panel
            //    swap) surfaces only the completed course, chip "Concluído",
            //    CTA "Baixar certificado" pointing at the issued certificate.
            $browser->click('@tab-concluidos')
                ->waitFor('@course-card-'.$completedCourse->id)
                ->assertMissing('@course-card-'.$inProgressCourse->id)
                ->assertMissing('@course-card-'.$expiredCourse->id)
                ->assertSeeIn('@course-continue-'.$completedCourse->id, 'Baixar certificado');

            // 3. "Todos" tab: every non-cancelled enrollment shows, including
            //    the expired one with its "Prazo encerrado" chip and the
            //    read-only "Ver o que você fez" CTA.
            $browser->click('@tab-todos')
                ->waitFor('@course-card-'.$expiredCourse->id)
                ->assertVisible('@course-card-'.$inProgressCourse->id)
                ->assertVisible('@course-card-'.$completedCourse->id)
                ->assertSeeIn('@course-status-'.$expiredCourse->id, 'Prazo encerrado')
                ->assertSeeIn('@course-continue-'.$expiredCourse->id, 'Ver o que você fez');
        });
    }

    public function test_student_courses_catalog_shows_contextual_empty_state_per_tab(): void
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->browse(function (Browser $browser) use ($student): void {
            $browser->loginAs($student)
                ->visit('/meus-cursos')
                ->waitFor('@no-enrollments')
                ->assertSee('Nenhum curso por aqui.');

            $browser->click('@tab-concluidos')
                ->waitFor('@no-enrollments');

            $browser->click('@tab-todos')
                ->waitFor('@no-enrollments');
        });
    }
}
