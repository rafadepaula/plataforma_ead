<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 *  E2E — upload a CSV, observe the chunked AJAX progress bar, and
 * verify the final course roster reflects every imported row.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de importação (upload válido → segundo upload com e-mail já
 * existente em outra Organização) é um método, as rejeições de arquivo são
 * exercitadas na mesma sessão de formulário em outro, e o bloqueio do Admin
 * sem contexto é negativa independente.
 *
 * Isolamento via `DatabaseTruncation` herdado de `Tests\DuskTestCase`
 * (nunca `RefreshDatabase` — Dusk dirige navegador e app como processos/
 * conexões HTTP separados).
 */
class MultiTenantStudentImportTest extends DuskTestCase
{
    /**
     * Arquivos temporários criados pelo teste, removidos no `tearDown()`.
     *
     * @var list<string>
     */
    private array $csvFixtures = [];

    public function test_csv_import_success_and_duplicate_handling_lifecycle(): void
    {
        // Usuário já existente em OUTRA Organização:  manda reaproveitar
        // a linha de `users` e nunca sobrescrever seu `org_id`.
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

        $firstCsv = $this->makeCsv("name,email,cpf\nMaria Aluna,maria.aluna@example.com,\nJoao Aluno,joao.aluno@example.com,\n");
        $secondCsv = $this->makeCsv("name,email,cpf\nNome Diferente No CSV,ja.existe@example.com,\nNova Aluna,nova.aluna@example.com,\n");

        $this->browse(function (Browser $browser) use ($gestor, $course, $firstCsv, $secondCsv, $existingUser, $otherOrg): void {
            // 1. Importação válida: 2 alunos novos entram na turma.
            $browser->loginAs($gestor)
                ->visit(route('users.import.create'))
                ->waitFor('[dusk="csv-import-form"]')
                ->select('@csv-course-select', (string) $course->id)
                ->attach('@csv-file-input', $firstCsv)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Importação concluída');

            $this->assertSame(2, $course->fresh()->students()->count());
            $this->assertDatabaseHas('users', ['email' => 'maria.aluna@example.com', 'org_id' => $course->org_id]);
            $this->assertDatabaseHas('users', ['email' => 'joao.aluno@example.com', 'org_id' => $course->org_id]);

            // 2. Segunda importação na mesma sessão: um e-mail já cadastrado em
            //    outra Organização é reaproveitado , o outro é criado.
            $browser->visit(route('users.import.create'))
                ->waitFor('[dusk="csv-import-form"]')
                ->select('@csv-course-select', (string) $course->id)
                ->attach('@csv-file-input', $secondCsv)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Importação concluída: 1 usuário(s) criado(s), 2 matrícula(s) realizada(s), 0 linha(s) ignorada(s).');

            // Nenhuma segunda linha de `users`, nome e org originais intactos.
            $this->assertSame(1, User::where('email', 'ja.existe@example.com')->count());
            $freshExistingUser = $existingUser->fresh();
            $this->assertSame('Nome Original', $freshExistingUser->name);
            $this->assertSame($otherOrg->id, $freshExistingUser->org_id);

            $newUser = User::where('email', 'nova.aluna@example.com')->firstOrFail();

            $this->assertDatabaseHas('course_user', [
                'user_id' => $existingUser->id,
                'course_id' => $course->id,
            ]);
            $this->assertDatabaseHas('course_user', [
                'user_id' => $newUser->id,
                'course_id' => $course->id,
            ]);
        });

        $this->assertSame(4, $course->fresh()->students()->count());
    }

    /**
     * As duas rejeições de arquivo exercitadas em sequência no MESMO
     * formulário: nenhuma delas pode criar usuário ou matrícula.
     */
    public function test_csv_import_validation_rejections(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $usersCountBefore = User::count();

        $wrongHeaderCsv = $this->makeCsv("nome,e-mail\nMaria Aluna,maria.aluna@example.com\n");
        $missingColumnCsv = $this->makeCsv("name,cpf\nMaria Aluna,12345678900\n");

        $this->browse(function (Browser $browser) use ($gestor, $course, $wrongHeaderCsv, $missingColumnCsv): void {
            // 1. Cabeçalho totalmente diferente do contrato.
            $browser->loginAs($gestor)
                ->visit(route('users.import.create'))
                ->waitFor('[dusk="csv-import-form"]')
                ->select('@csv-course-select', (string) $course->id)
                ->attach('@csv-file-input', $wrongHeaderCsv)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Cabeçalho inválido');

            // 2. Mesmo formulário, cabeçalho sem a coluna obrigatória `email`.
            $browser->attach('@csv-file-input', $missingColumnCsv)
                ->press('@csv-import-submit')
                ->waitFor('[dusk="csv-import-results"]', 15)
                ->assertSeeIn('[dusk="csv-import-results"]', 'Cabeçalho inválido');
        });

        $this->assertDatabaseCount('users', $usersCountBefore);
        $this->assertSame(0, $course->fresh()->students()->count());
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

    private function makeCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_import_').'.csv';
        file_put_contents($path, $contents);
        $this->csvFixtures[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->csvFixtures as $path) {
            if (file_exists($path)) {
                unlink($path);
            }
        }

        $this->csvFixtures = [];

        parent::tearDown();
    }
}
