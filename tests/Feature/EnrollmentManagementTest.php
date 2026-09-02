<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\EnrollmentConfirmedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Gestor/Admin panel for manually enrolling and revoking a
 * Course's `course_user` rows via `EnrollmentController`. Authorization is
 * against the parent `Course` (`CoursePolicy::update`) since `course_user`
 * is a pivot only — no dedicated `Enrollment` model/policy exists.
 */
class EnrollmentManagementTest extends TestCase
{
    public function test_gestor_can_view_the_enrollments_index_for_their_own_course(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->get(route('courses.enrollments.index', $course))
            ->assertOk()
            ->assertViewIs('courses.enrollments.index')
            ->assertSee($student->name);
    }

    public function test_gestor_can_manually_enroll_an_existing_user(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $response = $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ]);

        $response->assertRedirect(route('courses.enrollments.index', $course));
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
    }

    public function test_manually_enrolling_an_already_actively_enrolled_user_fails_validation(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');
    }

    public function test_manually_enrolling_a_previously_cancelled_user_reactivates_their_enrollment(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now()->subMonth(), 'status' => 'cancelled']);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $student->id,
        ])->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('course_user', 1);
    }

    public function test_gestor_can_revoke_an_active_enrollment(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->delete(route('courses.enrollments.destroy', [$course, $student]))
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * `CoursePolicy`/`CourseController::destroy()`'s "no active
     * enrollments" guard (`Course::hasActiveEnrollments()`) must stay
     * consistent with `EnrollmentController::destroy()`'s revocation
     * semantics: revoking a Course's only active enrollment (setting
     * `status = 'cancelled'`, never detaching the pivot row) should make
     * the Course deletable again.
     */
    public function test_revoking_the_only_active_enrollment_makes_the_course_deletable(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->from(route('courses.enrollments.index', $course))
            ->delete(route('courses.destroy', $course))
            ->assertRedirect(route('courses.enrollments.index', $course))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($course);

        $this->delete(route('courses.enrollments.destroy', [$course, $student]))
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->delete(route('courses.destroy', $course))->assertRedirect();
        $this->assertSoftDeleted($course);
    }

    public function test_aluno_is_forbidden_from_the_enrollments_panel(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->get(route('courses.enrollments.index', $course))->assertForbidden();
        $this->post(route('courses.enrollments.store', $course), ['user_id' => 1])->assertForbidden();
    }

    public function test_gestor_from_another_org_cannot_manage_enrollments_of_a_course_they_do_not_own(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherCourse = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // `OrgScope` on `Course` hides the row entirely for a Gestor of a
        // different org, so route-model binding itself 404s before
        // authorization ever runs — same pattern as
        // `MultiTenantCourseManagementTest`'s cross-tenant Course checks.
        $this->get(route('courses.enrollments.index', $otherCourse))->assertNotFound();
        $this->post(route('courses.enrollments.store', $otherCourse), ['user_id' => 1])->assertNotFound();
    }

    public function test_gestor_cannot_manually_enroll_a_user_from_a_different_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->create(['org_id' => $otherOrg->id]);
        $outsider->assignRole(RolesEnum::ALUNO->value);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $outsider->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_user', [
            'user_id' => $outsider->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_gestor_cannot_manually_enroll_a_staff_account(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $anotherGestor = User::factory()->create(['org_id' => $org->id]);
        $anotherGestor->assignRole(RolesEnum::GESTOR->value);

        $this->post(route('courses.enrollments.store', $course), [
            'user_id' => $anotherGestor->id,
        ])->assertRedirect()->assertSessionHasErrors('user_id');

        $this->assertDatabaseMissing('course_user', [
            'user_id' => $anotherGestor->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_guest_is_redirected_away_from_the_enrollments_panel(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);

        $this->get(route('courses.enrollments.index', $course))->assertRedirect();
    }

    public function test_search_matches_alunos_by_partial_name_email_and_cpf(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $byName = User::factory()->create(['org_id' => $org->id, 'name' => 'Mariana Souza']);
        $byName->assignRole(RolesEnum::ALUNO->value);
        $byEmail = User::factory()->create(['org_id' => $org->id, 'email' => 'carlos.mendes@example.com']);
        $byEmail->assignRole(RolesEnum::ALUNO->value);
        $byCpf = User::factory()->create(['org_id' => $org->id, 'cpf' => '52998224725']);
        $byCpf->assignRole(RolesEnum::ALUNO->value);

        // Nome parcial
        $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'Mariana']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $byName->id)
            ->assertJsonPath('data.0.enrollment_status', null);

        // E-mail parcial
        $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'carlos.mendes']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $byEmail->id);

        // CPF com máscara é normalizado para dígitos antes do LIKE
        $this->getJson(route('courses.enrollments.search', [$course, 'q' => '529.982.247-25']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $byCpf->id);
    }

    public function test_search_excludes_active_enrollments_and_non_aluno_accounts(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $activeStudent = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Ativo']);
        $activeStudent->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($activeStudent->id, ['enrolled_at' => now(), 'status' => 'active']);

        $cancelledStudent = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Cancelado']);
        $cancelledStudent->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($cancelledStudent->id, ['enrolled_at' => now(), 'status' => 'cancelled']);

        $staff = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Buscado']);
        $staff->assignRole(RolesEnum::GESTOR->value);

        $response = $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'Buscado']))
            ->assertOk();

        $this->assertStringNotContainsString('"name":"Gestor Buscado"', $response->getContent());
        $this->assertStringNotContainsString('"name":"Aluno Ativo"', $response->getContent());

        $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'Cancelado']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $cancelledStudent->id)
            ->assertJsonPath('data.0.enrollment_status', 'cancelled');
    }

    public function test_search_requires_a_query_term(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->getJson(route('courses.enrollments.search', $course))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    public function test_aluno_and_cross_org_gestor_cannot_use_the_search_endpoint(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);
        $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'a']))->assertForbidden();
        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'X',
            'email' => 'x@example.com',
            'cpf' => '52998224725',
        ])->assertForbidden();

        // `OrgScope` em `Course` esconde a linha de um Gestor de outra org:
        // o binding da rota 404 antes da autorização.
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);
        $this->getJson(route('courses.enrollments.search', [$course, 'q' => 'a']))->assertNotFound();
        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'X',
            'email' => 'x@example.com',
            'cpf' => '52998224725',
        ])->assertNotFound();
    }

    public function test_gestor_can_create_and_enroll_a_new_student_in_one_step(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'Nova Aluna',
            'email' => 'nova.aluna@example.com',
            'cpf' => '111.444.777-35',
        ])->assertRedirect(route('courses.enrollments.index', $course))
            ->assertSessionHas('success');

        $student = User::query()->where('email', 'nova.aluna@example.com')->firstOrFail();
        $this->assertSame($org->id, $student->org_id);
        $this->assertSame('11144477735', $student->cpf);
        $this->assertSame('active', $student->status);
        $this->assertTrue($student->hasRole(RolesEnum::ALUNO->value));
        // A senha inicial é o CPF normalizado em dígitos.
        $this->assertTrue(Hash::check('11144477735', $student->password));

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        Notification::assertSentTo($student, EnrollmentConfirmedNotification::class);
    }

    public function test_create_student_rejects_duplicate_email_and_cpf_variants(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $existing = User::factory()->create([
            'org_id' => $org->id,
            'email' => 'existente@example.com',
            'cpf' => '52998224725',
        ]);
        $existing->assignRole(RolesEnum::ALUNO->value);

        // Mesmo e-mail: rejeitado.
        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'Duplicado E-mail',
            'email' => 'existente@example.com',
            'cpf' => '11144477735',
        ])->assertRedirect()->assertSessionHasErrors('email');

        // Mesmo CPF digitado com máscara: `Cpf::digits()` normaliza antes
        // do `unique`, então a variante mascarada não escapa da checagem.
        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'Duplicado CPF',
            'email' => 'outro@example.com',
            'cpf' => '529.982.247-25',
        ])->assertRedirect()->assertSessionHasErrors('cpf');

        // CPF com dígito verificador inválido: rejeitado.
        $this->post(route('courses.enrollments.store-student', $course), [
            'name' => 'CPF Inválido',
            'email' => 'invalido@example.com',
            'cpf' => '52998224724',
        ])->assertRedirect()->assertSessionHasErrors('cpf');

        $this->assertDatabaseMissing('users', ['email' => 'outro@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'invalido@example.com']);
        $this->assertDatabaseMissing('course_user', ['course_id' => $course->id]);
    }
}
