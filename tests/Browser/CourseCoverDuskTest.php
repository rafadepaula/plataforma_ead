<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the course cover ("Capa do curso", task 86e361fwe,
 * Frente D): a Gestor uploads a cover image on `courses.edit` and the
 * enrolled Aluno sees it on the "Meus Cursos" card instead of the pastel
 * wash; a second chain covers removal (DB `cover_path` nulled, file gone
 * from disk, card back to the wash).
 *
 * Split into TWO methods on purpose (deviation allowed by the task): a
 * single chain would need Gestor → Aluno → Gestor → Aluno, i.e. TWO
 * logins for the same actor, breaking the ≤1-login-per-actor budget from
 * `testing-conventions`. Each method below logs each actor in exactly
 * once and seeds its own state, so no method depends on another
 * (`DatabaseTruncation` wipes the DB between them).
 *
 * Contract dependency on Frente C (NOT implemented yet at the time of
 * writing — see return notes): `courses.edit` must render
 * `@course-cover-input` (file input), `@course-cover-preview` (visible
 * when a cover exists) and `@course-cover-remove` (assumed to be a
 * checkbox inside `@course-form`, submitted via `@course-submit`), with
 * `enctype="multipart/form-data"` on the form, `cover` validation in
 * `UpdateCourseRequest`, and persistence/removal through
 * `FileUploadService::storeCover()` on the `public` disk. Until Frente C
 * lands, both methods below fail at `waitFor('@course-form')`'s
 * `@course-cover-input` step — that failure IS the contract signal.
 *
 * Isolamento via `DatabaseTruncation` herdado de `Tests\DuskTestCase`
 * (nunca `RefreshDatabase` — Dusk dirige navegador e app como processos/
 * conexões HTTP separados). Cover files live on the REAL `testing`-env
 * `public` disk (no fake — the HTTP process would never see it), so
 * created paths are tracked in `$coverPaths` and deleted in `tearDown()`.
 */
class CourseCoverDuskTest extends DuskTestCase
{
    /**
     * Cover files written to the real `public` disk by these chains,
     * removed in `tearDown()` so no binary leaks between methods.
     *
     * @var list<string>
     */
    private array $coverPaths = [];

    public function test_gestor_course_cover_upload_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->gestor()->inOrg($org->id)->create();
        $aluno = User::factory()->aluno()->create();
        $course = Course::factory()->published()->create([
            'org_id' => $org->id,
            'title' => 'Curso Capa Dusk',
        ]);
        $course->students()->attach($aluno->id, ['enrolled_at' => now(), 'status' => 'active']);

        $fixture = realpath(base_path('tests/fixtures/course-cover.png'));
        $this->assertIsString($fixture);

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $course, $fixture): void {
            // 1. Gestor anexa a capa em `courses.edit` e salva.
            $browser->loginAs($gestor)
                ->visit(route('courses.edit', $course))
                ->waitFor('@course-form')
                ->attach('@course-cover-input', $fixture)
                ->press('@course-submit')
                ->waitForLocation('/courses')
                ->assertSee('Curso atualizado com sucesso.');

            $coverPath = Course::withoutGlobalScopes()->findOrFail($course->id)->cover_path;

            $this->assertNotNull($coverPath);
            $this->assertDatabaseHas('courses', [
                'id' => $course->id,
                'cover_path' => $coverPath,
            ]);
            Storage::disk('public')->assertExists($coverPath);

            $this->coverPaths[] = $coverPath;

            // 2. A tela de edição passa a exibir o preview da capa.
            $browser->visit(route('courses.edit', $course))
                ->waitFor('@course-form')
                ->assertVisible('@course-cover-preview');

            // 3. O aluno matriculado vê a capa no card de "Meus Cursos"
            //    em vez do wash em pastel.
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@course-card-'.$course->id)
                ->assertSeeIn('@course-status-'.$course->id, 'Não iniciado')
                ->assertPresent('[dusk="course-card-'.$course->id.'"] img.ds-course-card-cover')
                ->assertMissing('[dusk="course-card-'.$course->id.'"] .ds-pastel-wash');
        });
    }

    public function test_gestor_course_cover_removal_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->gestor()->inOrg($org->id)->create();
        $aluno = User::factory()->aluno()->create();
        $course = Course::factory()->published()->create([
            'org_id' => $org->id,
            'title' => 'Curso Remove Capa Dusk',
        ]);
        $course->students()->attach($aluno->id, ['enrolled_at' => now(), 'status' => 'active']);

        $seedPath = "orgs/{$org->id}/courses/{$course->id}/cover/seed.png";
        Storage::disk('public')->put(
            $seedPath,
            (string) file_get_contents((string) base_path('tests/fixtures/course-cover.png'))
        );
        $course->update(['cover_path' => $seedPath]);

        $this->coverPaths[] = $seedPath;

        $this->browse(function (Browser $browser) use ($gestor, $aluno, $course, $seedPath): void {
            // 1. Gestor marca a remoção da capa em `courses.edit` e salva.
            $browser->loginAs($gestor)
                ->visit(route('courses.edit', $course))
                ->waitFor('@course-form')
                ->assertVisible('@course-cover-preview')
                ->check('@course-cover-remove')
                ->press('@course-submit')
                ->waitForLocation('/courses')
                ->assertSee('Curso atualizado com sucesso.');

            $this->assertDatabaseHas('courses', [
                'id' => $course->id,
                'cover_path' => null,
            ]);
            Storage::disk('public')->assertMissing($seedPath);

            // 2. O card do aluno volta ao wash em pastel, sem `img` de capa.
            $browser->loginAs($aluno)
                ->visit(route('student.courses.index'))
                ->waitFor('@course-card-'.$course->id)
                ->assertMissing('[dusk="course-card-'.$course->id.'"] img.ds-course-card-cover')
                ->assertPresent('[dusk="course-card-'.$course->id.'"] .ds-pastel-wash');
        });
    }

    protected function tearDown(): void
    {
        foreach ($this->coverPaths as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        $this->coverPaths = [];

        parent::tearDown();
    }
}
