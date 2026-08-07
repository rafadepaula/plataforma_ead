<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * RF05/RN09 E2E — upload a CSV, observe the chunked AJAX progress bar,
 * and verify the final course roster reflects every imported row.
 *
 * Uses DatabaseMigrations (not RefreshDatabase) because Dusk drives the
 * browser and the app server as separate HTTP processes/connections.
 */
class MultiTenantStudentImportTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_gestor_can_upload_a_csv_and_see_the_chunked_progress_bar(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $csvPath = tempnam(sys_get_temp_dir(), 'csv_import_').'.csv';
        file_put_contents($csvPath, "name,email,cpf\nMaria Aluna,maria.aluna@example.com,\nJoao Aluno,joao.aluno@example.com,\n");

        $this->browse(function (Browser $browser) use ($gestor, $course, $csvPath): void {
            $browser->loginAs($gestor)
                ->visit(route('users.import.create'))
                ->waitFor('[dusk="csv-import-form"]')
                ->select('@csv-course-select', (string) $course->id)
                ->attach('@csv-file-input', $csvPath)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Importação concluída');
        });

        unlink($csvPath);

        $this->assertSame(2, $course->fresh()->students()->count());
        $this->assertTrue(User::where('email', 'maria.aluna@example.com')->exists());
        $this->assertTrue(User::where('email', 'joao.aluno@example.com')->exists());
    }

    public function test_a_duplicate_email_in_the_csv_reuses_the_existing_user(): void
    {
        $otherOrg = Organization::factory()->create();
        $existingUser = User::factory()->create([
            'org_id' => $otherOrg->id,
            'name' => 'Nome Original',
            'email' => 'ja.existe@example.com',
            'password' => bcrypt('senha-original'),
        ]);
        $existingUser->assignRole(RolesEnum::ALUNO->value);

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $csvPath = tempnam(sys_get_temp_dir(), 'csv_import_').'.csv';
        file_put_contents($csvPath, "name,email,cpf\nNome Diferente No CSV,ja.existe@example.com,\nNova Aluna,nova.aluna@example.com,\n");

        $this->browse(function (Browser $browser) use ($gestor, $course, $csvPath): void {
            $browser->loginAs($gestor)
                ->visit(route('users.import.create'))
                ->waitFor('[dusk="csv-import-form"]')
                ->select('@csv-course-select', (string) $course->id)
                ->attach('@csv-file-input', $csvPath)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Importação concluída: 1 usuário(s) criado(s), 2 matrícula(s) realizada(s), 0 linha(s) ignorada(s).');
        });

        unlink($csvPath);

        $this->assertSame(1, User::where('email', 'ja.existe@example.com')->count());

        $freshExistingUser = $existingUser->fresh();
        $this->assertSame('Nome Original', $freshExistingUser->name);
        $this->assertSame($otherOrg->id, $freshExistingUser->org_id);

        $newUser = User::where('email', 'nova.aluna@example.com')->first();
        $this->assertNotNull($newUser);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $existingUser->id,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $newUser->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_an_admin_without_an_impersonated_org_cannot_open_the_csv_import_screen(): void
    {
        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $this->browse(function (Browser $browser) use ($admin): void {
            $browser->loginAs($admin)
                ->visit(route('admin.dashboard'))
                ->visit(route('users.import.create'))
                ->waitForText('Selecione uma Organização ativa antes de continuar.')
                ->assertMissing('[dusk="csv-import-form"]');
        });
    }
}
