<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Services\UserImportService;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * chunked (50-row) CSV import of Alunos, multi-org adaptive
 * enrollment: a globally-existing e-mail must only gain a new course
 * enrollment (never a duplicate User row, never an overwritten password);
 * a brand-new e-mail creates the User (bound to the current org) and
 * enrolls it.
 */
class MultiTenantStudentImportTest extends TestCase
{
    public function test_gestor_can_view_the_import_form_scoped_to_their_own_orgs_courses(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        Course::factory()->inOrg($org->id)->create(['title' => 'Curso da Minha Org']);
        Course::factory()->inOrg($otherOrg->id)->create(['title' => 'Curso de Outra Org']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get('/users/import')
            ->assertOk()
            ->assertViewIs('users.import')
            ->assertSee('Curso da Minha Org')
            ->assertDontSee('Curso de Outra Org');
    }

    public function test_import_reactivates_an_inactive_account_in_the_importing_org(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        $student = User::factory()->aluno()->inOrg($org)->withPassword('senha-antiga')->inactive()->create([
            'email' => 'inativa@example.com',
        ]);

        $service = new UserImportService;
        $result = $service->importChunk(
            rows: [['name' => 'Aluna Inativa', 'email' => 'inativa@example.com']],
            courseId: $course->id,
            orgId: $org->id,
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['enrolled']);

        // reimportar = (re)dar acesso: a conta volta a ficar ativa
        $this->assertSame('active', $student->fresh()->credentialFor($org)->status);
        $this->assertSame(1, $course->students()->count());
    }

    public function test_existing_global_email_enrolls_in_new_orgs_course_without_duplicating_user_or_overwriting_password(): void
    {
        $originalOrg = Organization::factory()->create();
        $newOrg = Organization::factory()->create();
        $newOrgCourse = Course::factory()->inOrg($newOrg->id)->create();

        $existingPasswordHash = Hash::make('original-secret-password');
        $existingUser = User::factory()->aluno()->inOrg($originalOrg)->withPassword('original-secret-password')->create([
            'email' => 'aluno@example.com',
        ]);
        $existingPasswordHash = $existingUser->credentialFor($originalOrg)->password;

        $service = new UserImportService;
        $result = $service->importChunk(
            rows: [['name' => 'Aluno Exemplo', 'email' => 'aluno@example.com']],
            courseId: $newOrgCourse->id,
            orgId: $newOrg->id,
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['enrolled']);
        $this->assertCount(1, User::where('email', 'aluno@example.com')->get());

        $existingUser->refresh();
        $this->assertNotNull($existingUser->credentialFor($originalOrg));
        // a importação provisiona conta na org importadora (senha aleatória)
        $this->assertNotNull($existingUser->credentialFor($newOrg));
        $this->assertSame($existingPasswordHash, $existingUser->credentialFor($originalOrg)->password);
        $this->assertTrue($existingUser->courses()->where('course_id', $newOrgCourse->id)->exists());
    }

    public function test_new_email_creates_user_with_current_org_id_and_enrolls(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        $service = new UserImportService;
        $result = $service->importChunk(
            rows: [['name' => 'Novo Aluno', 'email' => 'novo@example.com']],
            courseId: $course->id,
            orgId: $org->id,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['enrolled']);

        $user = User::where('email', 'novo@example.com')->firstOrFail();
        $this->assertNotNull($user->credentialFor($org));
        $this->assertTrue($user->hasRole(RolesEnum::ALUNO->value));
        $this->assertTrue($user->courses()->where('course_id', $course->id)->exists());
    }

    public function test_chunk_boundary_of_exactly_50_rows_processes_every_row(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $rows[] = ['name' => "Aluno {$i}", 'email' => "aluno{$i}@example.com"];
        }

        $service = new UserImportService;
        $result = $service->importChunk(rows: $rows, courseId: $course->id, orgId: $org->id);

        $this->assertSame(50, $result['created']);
        $this->assertSame(50, $result['enrolled']);
        $this->assertSame(50, $course->students()->count());
    }

    public function test_malformed_rows_are_skipped_without_aborting_the_whole_chunk(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        $service = new UserImportService;
        $result = $service->importChunk(
            rows: [
                ['name' => '', 'email' => 'sem-nome@example.com'],
                ['name' => 'Sem Email', 'email' => ''],
                ['name' => 'Email Invalido', 'email' => 'not-an-email'],
                ['name' => 'Valido', 'email' => 'valido@example.com'],
            ],
            courseId: $course->id,
            orgId: $org->id,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['enrolled']);
        $this->assertCount(3, $result['skipped']);
        $this->assertTrue(User::where('email', 'valido@example.com')->exists());
    }

    public function test_admin_without_active_org_context_gets_unresolved_org_context_via_import_endpoint(): void
    {
        $course = Course::factory()->inOrg(Organization::factory()->create()->id)->create();

        $this->actingAsAdmin();

        $response = $this->postJson('/users/import/chunk', [
            'course_id' => $course->id,
            'rows' => [['name' => 'X', 'email' => 'x@example.com']],
        ]);

        $response->assertStatus(422);
    }

    public function test_gestor_import_chunk_endpoint_enrolls_students_into_own_org_course(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->postJson('/users/import/chunk', [
            'course_id' => $course->id,
            'rows' => [['name' => 'Fulano', 'email' => 'fulano@example.com']],
        ]);

        $response->assertOk();
        $response->assertJson(['created' => 1, 'enrolled' => 1]);

        $user = User::where('email', 'fulano@example.com')->firstOrFail();
        $this->assertNotNull($user->credentialFor($org));
    }
}
