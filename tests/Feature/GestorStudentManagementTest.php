<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use App\Notifications\EnrollmentConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 *  the Organizador's exclusive Aluno directory
 * (`gestor.students.*`, `role:gestor`). The screen lists ONLY the Alunos
 * enrolled in the acting Gestor's own Organization's Courses, and the
 * Organizador may view/manage exactly those — never another staff
 * account, never a foreign tenant. Route `role:` middleware is the first
 * defense, `UserPolicy`'s `*Student` abilities the second.
 */
class GestorStudentManagementTest extends TestCase
{
    private function enrolledAluno(Organization $org, ?string $name = null, string $pivotStatus = 'active'): User
    {
        $course = Course::factory()->for($org)->create();
        $aluno = User::factory()->inOrg($org)->create($name !== null ? ['name' => $name] : []);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => $pivotStatus, 'enrolled_at' => now()]);

        return $aluno;
    }

    // ── Listing scope ────────────────────────────────────────────────

    public function test_listing_shows_only_own_orgs_enrolled_alunos(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $enrolled = $this->enrolledAluno($org, 'Aluno Matriculado');
        // own-org Aluno with a cancelled (revoked) enrollment.
        $cancelled = $this->enrolledAluno($org, 'Aluno Cancelado', 'cancelled');
        // own-org Aluno with no enrollment at all.
        $unEnrolled = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Sem Matrícula']);
        $unEnrolled->assignRole(RolesEnum::ALUNO->value);
        // a foreign-org Aluno.
        $this->enrolledAluno($otherOrg, 'Aluno De Outra Org');
        // an own-org staff account (never listed on this screen).
        $fellowGestor = User::factory()->inOrg($org->id)->create(['name' => 'Gestor Colega']);
        $fellowGestor->assignRole(RolesEnum::GESTOR->value);

        $response = $this->actingAs($gestor)->get(route('gestor.students.index'));

        $response->assertOk();
        $response->assertViewIs('gestor.students.index');
        $response->assertSee('Aluno Matriculado');
        $response->assertDontSee('Aluno Cancelado');
        $response->assertDontSee('Aluno Sem Matrícula');
        $response->assertDontSee('Aluno De Outra Org');
        $response->assertDontSee('Gestor Colega');
        $this->assertNull($response->viewData('students')->firstWhere('id', $cancelled->id));
        $this->assertNull($response->viewData('students')->firstWhere('id', $unEnrolled->id));
        $this->assertNotNull($response->viewData('students')->firstWhere('id', $enrolled->id));
    }

    public function test_listing_shows_a_completed_enrollment_still_as_enrolled(): void
    {
        //  `cancelled` is the revoked state; `completed` is a
        // finished Course — the Aluno remains enrolled (history kept by
        // the pivot's soft-status design), so they stay on the screen.
        $org = Organization::factory()->create();
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $aluno = $this->enrolledAluno($org, 'Aluno Formado', 'completed');

        $this->actingAs($gestor)
            ->get(route('gestor.students.index'))
            ->assertOk()
            ->assertSee('Aluno Formado');
    }

    // ── Search ───────────────────────────────────────────────────────

    public function test_search_filters_alunos_by_partial_name(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $maria = $this->enrolledAluno($org, 'Maria Souza');
        $this->enrolledAluno($org, 'João Pereira');

        $response = $this->get(route('gestor.students.index', ['search' => 'Maria']));

        $response->assertOk();
        $this->assertNotNull($response->viewData('students')->firstWhere('id', $maria->id));
        $this->assertNull($response->viewData('students')->firstWhere('name', 'João Pereira'));
    }

    public function test_search_matches_by_email_and_by_formatted_cpf(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        // CPF stored digits-only, searched with the formatted mask.
        $byCpf = $this->enrolledAluno($org, 'Aluno Um');
        $byCpf->update(['cpf' => '52998224725']);

        $byEmail = $this->enrolledAluno($org, 'Aluno Dois');
        $byEmail->update(['email' => 'aluno.dois@example.com']);

        $response = $this->get(route('gestor.students.index', ['search' => '529.982.247-25']));
        $this->assertNotNull($response->viewData('students')->firstWhere('id', $byCpf->id));
        $this->assertNull($response->viewData('students')->firstWhere('name', 'Aluno Dois'));

        $response = $this->get(route('gestor.students.index', ['search' => 'aluno.dois@example.com']));
        $this->assertNotNull($response->viewData('students')->firstWhere('id', $byEmail->id));
        $this->assertNull($response->viewData('students')->firstWhere('id', $byCpf->id));
    }

    public function test_search_never_reaches_beyond_the_own_orgs_enrolled_alunos(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->enrolledAluno($otherOrg, 'Maria De Outra Org');

        $response = $this->get(route('gestor.students.index', ['search' => 'Maria']));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('students')->count());
    }

    // ── Authorization boundaries ─────────────────────────────────────

    public function test_gestor_cannot_edit_or_delete_a_foreign_orgs_aluno(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $foreign = $this->enrolledAluno($otherOrg, 'Aluno Alheio');

        $this->get(route('gestor.students.edit', $foreign))->assertForbidden();
        $this->put(route('gestor.students.update', $foreign), ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        $this->delete(route('gestor.students.destroy', $foreign))->assertForbidden();
    }

    public function test_gestor_cannot_manage_a_fellow_gestor_via_the_students_routes(): void
    {
        //  "gerenciar os alunos ... e nada mais": the
        // Organizador manages ALUNO accounts only. A same-org staff
        // account must 403 even though it shares the tenant — that
        // restriction lives in `UserPolicy::managesSameOrgAluno()`, not
        // just in the listing query.
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $fellowGestor = User::factory()->inOrg($org->id)->create();
        $fellowGestor->assignRole(RolesEnum::GESTOR->value);

        $this->get(route('gestor.students.edit', $fellowGestor))->assertForbidden();
        $this->put(route('gestor.students.update', $fellowGestor), ['name' => 'x', 'email' => 'x@example.com'])->assertForbidden();
        $this->delete(route('gestor.students.destroy', $fellowGestor))->assertForbidden();
    }

    public function test_gestor_cannot_promote_an_aluno_via_the_students_update(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = $this->enrolledAluno($org);

        // `role` is not on this screen's validation surface: posting a
        // spoofed role must neither change it nor fail the request.
        $this->put(route('gestor.students.update', $aluno), [
            'name' => $aluno->name,
            'email' => $aluno->email,
            'role' => RolesEnum::GESTOR->value,
        ])->assertRedirect(route('gestor.students.index'));

        $aluno->refresh();
        $this->assertFalse($aluno->hasRole(RolesEnum::GESTOR->value));
        $this->assertTrue($aluno->hasRole(RolesEnum::ALUNO->value));
    }

    public function test_admin_is_forbidden_from_the_gestor_students_routes(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin($org);

        $aluno = $this->enrolledAluno($org);

        $this->get(route('gestor.students.index'))->assertForbidden();
        $this->get(route('gestor.students.edit', $aluno))->assertForbidden();
    }

    public function test_edit_screen_renders_for_an_enrolled_aluno_of_the_own_org(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = $this->enrolledAluno($org);

        $this->get(route('gestor.students.edit', $aluno))
            ->assertOk()
            ->assertViewIs('gestor.students.edit')
            ->assertSee($aluno->name);
    }

    // ── Directory-level create (Cadastrar aluno) ─────────────────────

    public function test_create_screen_lists_only_own_org_courses(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $ownCourse = Course::factory()->for($org)->create(['title' => 'Curso Próprio']);
        Course::factory()->for($otherOrg)->create(['title' => 'Curso Alheio']);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get(route('gestor.students.create'))
            ->assertOk()
            ->assertViewIs('gestor.students.create')
            ->assertSee('Curso Próprio')
            ->assertDontSee('Curso Alheio');
    }

    public function test_gestor_can_create_a_student_already_enrolled_in_a_course(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->for($org)->create();

        $this->post(route('gestor.students.store'), [
            'name' => 'Aluna Direta',
            'email' => 'aluna.direta@example.com',
            'cpf' => '111.444.777-35',
            'course_id' => $course->id,
        ])->assertRedirect(route('gestor.students.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('invitation_url');

        $student = User::query()->where('email', 'aluna.direta@example.com')->firstOrFail();
        $this->assertSame('11144477735', $student->cpf);
        $this->assertTrue($student->hasRole(RolesEnum::ALUNO->value));

        $credential = $student->credentialFor($org);
        $this->assertNotNull($credential);
        $this->assertSame('pending', $credential->status);

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);

        $invitation = StudentInvitation::query()->where('user_id', $student->id)->firstOrFail();
        $this->assertSame($org->id, $invitation->org_id);
        $this->assertStringContainsString($invitation->token, session('invitation_url'));

        Notification::assertSentTo($student, EnrollmentConfirmedNotification::class);
    }

    public function test_create_links_a_multi_org_person_instead_of_rejecting(): void
    {
        $orgA = Organization::factory()->create();
        $person = User::factory()->aluno()->create([
            'email' => 'multi@example.com',
            'cpf' => '52998224725',
        ]);
        Credential::factory()->pending()->create(['user_id' => $person->id, 'org_id' => $orgA->id]);

        $orgB = Organization::factory()->create();
        $this->actingAsOrgUser($orgB, RolesEnum::GESTOR->value);
        $courseB = Course::factory()->for($orgB)->create();

        // Mesma pessoa (e-mail + CPF conferem), portal diferente: vincula,
        // não rejeita — 1 registro em `users`, 2 em `credentials`.
        $this->post(route('gestor.students.store'), [
            'name' => 'Pessoa Multi',
            'email' => 'multi@example.com',
            'cpf' => '529.982.247-25',
            'course_id' => $courseB->id,
        ])->assertRedirect(route('gestor.students.index'))
            ->assertSessionHas('success', 'Aluno já existente na plataforma: conta vinculada e matriculada com sucesso.')
            ->assertSessionHas('invitation_url');

        $this->assertSame(1, User::query()->where('email', 'multi@example.com')->count());
        $this->assertSame(1, Credential::query()->forOrg($orgA->id)->where('user_id', $person->id)->count());
        $this->assertSame(1, Credential::query()->forOrg($orgB->id)->where('user_id', $person->id)->count());
        $this->assertSame('pending', $person->fresh()->credentialFor($orgB)->status);
    }

    public function test_create_rejects_conflicting_identity_and_foreign_course(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $foreignCourse = Course::factory()->for($otherOrg)->create();

        User::factory()->aluno()->create(['email' => 'tomada@example.com', 'cpf' => '52998224725']);

        // E-mail já registrado com CPF diferente: não é a mesma pessoa.
        $this->post(route('gestor.students.store'), [
            'name' => 'Duplicado E-mail',
            'email' => 'tomada@example.com',
            'cpf' => '11144477735',
            'course_id' => Course::factory()->for($org)->create()->id,
        ])->assertRedirect()->assertSessionHasErrors('email');

        // CPF de outra pessoa: rejeitado.
        $this->post(route('gestor.students.store'), [
            'name' => 'Duplicado CPF',
            'email' => 'outro@example.com',
            'cpf' => '529.982.247-25',
            'course_id' => Course::factory()->for($org)->create()->id,
        ])->assertRedirect()->assertSessionHasErrors('cpf');

        // Curso de outra organização: rejeitado (nem dica de que existe).
        $this->post(route('gestor.students.store'), [
            'name' => 'Curso Alheio',
            'email' => 'alheio@example.com',
            'cpf' => '11144477735',
            'course_id' => $foreignCourse->id,
        ])->assertRedirect()->assertSessionHasErrors('course_id');

        $this->assertDatabaseMissing('users', ['email' => 'outro@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'alheio@example.com']);
        $this->assertSame(0, StudentInvitation::query()->count());
        $this->assertNull(User::where('email', 'tomada@example.com')->first()->credentialFor($org));
    }

    public function test_aluno_cannot_reach_the_directory_create_screen(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->get(route('gestor.students.create'))->assertForbidden();
        $this->post(route('gestor.students.store'), [])->assertForbidden();
    }
}
