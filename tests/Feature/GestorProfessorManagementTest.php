<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 *  the Organizador's exclusive Professor
 * directory (`gestor.professors.*`, `role:gestor`). It lists ONLY the
 * `professor` accounts of the acting Gestor's own Organization and lets
 * them create/edit/delete exactly those — never a foreign tenant and
 * never another staff account (a guessed same-org Gestor/Aluno URL 404s).
 * The created professor's `org_id` is resolved server-side by
 * `ResolvesOrgContext`, never trusted from request input.
 */
class GestorProfessorManagementTest extends TestCase
{
    private function ownProfessor(Organization $org, ?string $name = null, string $status = 'active'): User
    {
        $professor = User::factory()->create(
            ['org_id' => $org->id, 'status' => $status]
            + ($name !== null ? ['name' => $name] : []),
        );
        $professor->assignRole(RolesEnum::PROFESSOR->value);

        return $professor;
    }

    // ── Listing scope ────────────────────────────────────────────────

    public function test_listing_shows_only_the_own_orgs_professors(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $own = $this->ownProfessor($org, 'Professor Próprio');
        $foreign = $this->ownProfessor($otherOrg, 'Professor Alheio');
        // Same-org accounts that are NOT professors are never listed here.
        $fellowGestor = User::factory()->create(['org_id' => $org->id, 'name' => 'Gestor Colega']);
        $fellowGestor->assignRole(RolesEnum::GESTOR->value);
        $aluno = User::factory()->create(['org_id' => $org->id, 'name' => 'Aluno Intragrupo']);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $response = $this->get(route('gestor.professors.index'));

        $response->assertOk();
        $response->assertViewIs('gestor.professors.index');
        $response->assertSee('Professor Próprio');
        $response->assertDontSee('Professor Alheio');
        $response->assertDontSee('Gestor Colega');
        $response->assertDontSee('Aluno Intragrupo');

        $professors = $response->viewData('professors');
        $this->assertNotNull($professors->firstWhere('id', $own->id));
        $this->assertNull($professors->firstWhere('id', $foreign->id));
        $this->assertNull($professors->firstWhere('id', $fellowGestor->id));
        $this->assertNull($professors->firstWhere('id', $aluno->id));
    }

    public function test_search_filters_professors_by_partial_name(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $maria = $this->ownProfessor($org, 'Maria Docente');
        $this->ownProfessor($org, 'João Docente');

        $response = $this->get(route('gestor.professors.index', ['search' => 'Maria']));

        $response->assertOk();
        $professors = $response->viewData('professors');
        $this->assertNotNull($professors->firstWhere('id', $maria->id));
        $this->assertNull($professors->firstWhere('name', 'João Docente'));
    }

    public function test_search_matches_by_email(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $byEmail = $this->ownProfessor($org);
        $byEmail->update(['email' => 'prof.alvo@example.com']);
        $other = $this->ownProfessor($org);

        $response = $this->get(route('gestor.professors.index', ['search' => 'prof.alvo@example.com']));

        $response->assertOk();
        $professors = $response->viewData('professors');
        $this->assertNotNull($professors->firstWhere('id', $byEmail->id));
        $this->assertNull($professors->firstWhere('id', $other->id));
    }

    public function test_search_never_reaches_beyond_the_own_orgs_professors(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->ownProfessor($otherOrg, 'Maria De Outra Org');

        $response = $this->get(route('gestor.professors.index', ['search' => 'Maria']));

        $response->assertOk();
        $this->assertSame(0, $response->viewData('professors')->count());
    }

    // ── Create ───────────────────────────────────────────────────────

    public function test_gestor_can_create_a_professor_in_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get(route('gestor.professors.create'))->assertOk();

        $response = $this->post(route('gestor.professors.store'), [
            'name' => 'Novo Professor',
            'email' => 'novo.professor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // Spoofed fields: `org_id` is resolved server-side and neither
            // `role` nor `status` is part of this screen's surface.
            'org_id' => $otherOrg->id,
            'role' => RolesEnum::ADMIN->value,
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('gestor.professors.index'));

        $professor = User::where('email', 'novo.professor@example.com')->firstOrFail();
        $this->assertSame($org->id, $professor->org_id);
        $this->assertNotSame($otherOrg->id, $professor->org_id);
        $this->assertTrue($professor->hasRole(RolesEnum::PROFESSOR->value));
        $this->assertFalse($professor->hasRole(RolesEnum::ADMIN->value));
        $this->assertSame('active', $professor->status);
        $this->assertTrue(Hash::check('password123', $professor->password));
    }

    // ── Validation ───────────────────────────────────────────────────

    public function test_creating_a_professor_with_an_already_used_email_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $existing = $this->ownProfessor($org);

        $response = $this->post(route('gestor.professors.store'), [
            'name' => 'Duplicado',
            'email' => $existing->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', $existing->email)->count());
    }

    public function test_creating_a_professor_with_a_short_password_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->post(route('gestor.professors.store'), [
            'name' => 'Senha Curta',
            'email' => 'senha.curta@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'senha.curta@example.com')->exists());
    }

    public function test_creating_a_professor_with_a_mismatched_password_confirmation_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $response = $this->post(route('gestor.professors.store'), [
            'name' => 'Confirmacao Divergente',
            'email' => 'confirmacao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password456',
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'confirmacao@example.com')->exists());
    }

    // ── Edit / status audit ──────────────────────────────────────────

    public function test_gestor_can_edit_their_own_orgs_professor(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $professor = $this->ownProfessor($org);

        $this->get(route('gestor.professors.edit', $professor))
            ->assertOk()
            ->assertViewIs('gestor.professors.edit')
            ->assertSee($professor->name);

        $response = $this->put(route('gestor.professors.update', $professor), [
            'name' => 'Nome Docente Atualizado',
            'email' => $professor->email,
        ]);

        $response->assertRedirect(route('gestor.professors.index'));
        $professor->refresh();
        $this->assertSame('Nome Docente Atualizado', $professor->name);
        $this->assertSame($org->id, $professor->org_id);
    }

    public function test_deactivating_a_professor_records_the_user_status_changed_audit_event(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $professor = $this->ownProfessor($org, 'Docente Ativo');

        AuditLog::query()->delete();

        $this->put(route('gestor.professors.update', $professor), [
            'name' => $professor->name,
            'email' => $professor->email,
            'status' => 'inactive',
            'reason' => 'Licença sem vencimento',
        ])->assertRedirect(route('gestor.professors.index'));

        $this->assertSame('inactive', $professor->fresh()->status);

        $log = AuditLog::withoutGlobalScopes()
            ->where('event', 'user.status_changed')
            ->where('org_id', $org->id)
            ->first();

        $this->assertNotNull($log, 'Expected a user.status_changed audit row (GestorProfessorController::update()).');
        $this->assertSame($professor->id, $log->new_values['user_id']);
        $this->assertSame('active', $log->new_values['old_status']);
        $this->assertSame('inactive', $log->new_values['new_status']);
        $this->assertSame('Licença sem vencimento', $log->new_values['reason']);
    }

    public function test_saving_a_professor_without_changing_the_status_records_no_audit_event(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $professor = $this->ownProfessor($org, 'Docente Quietinho');

        AuditLog::query()->delete();

        $this->put(route('gestor.professors.update', $professor), [
            'name' => 'Docente Renomeado',
            'email' => $professor->email,
            'status' => 'active',
        ])->assertRedirect(route('gestor.professors.index'));

        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('event', 'user.status_changed')->count());
    }

    // ── Authorization boundaries ─────────────────────────────────────

    public function test_gestor_cannot_edit_or_delete_a_foreign_orgs_professor(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $foreign = $this->ownProfessor($otherOrg, 'Professor Alheio');

        $this->get(route('gestor.professors.edit', $foreign))->assertForbidden();
        $this->put(route('gestor.professors.update', $foreign), [
            'name' => 'x',
            'email' => 'x@example.com',
        ])->assertForbidden();
        $this->delete(route('gestor.professors.destroy', $foreign))->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $foreign->id]);
    }

    public function test_gestor_cannot_manage_a_fellow_gestor_via_a_forged_professor_url(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $fellowGestor = User::factory()->create(['org_id' => $org->id]);
        $fellowGestor->assignRole(RolesEnum::GESTOR->value);

        $this->get(route('gestor.professors.edit', $fellowGestor))->assertNotFound();
        $this->put(route('gestor.professors.update', $fellowGestor), [
            'name' => 'x',
            'email' => 'x@example.com',
        ])->assertNotFound();
        $this->delete(route('gestor.professors.destroy', $fellowGestor))->assertNotFound();
    }

    public function test_gestor_cannot_manage_an_aluno_via_a_forged_professor_url(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);

        $this->get(route('gestor.professors.edit', $aluno))->assertNotFound();
        $this->put(route('gestor.professors.update', $aluno), [
            'name' => 'x',
            'email' => 'x@example.com',
        ])->assertNotFound();
        $this->delete(route('gestor.professors.destroy', $aluno))->assertNotFound();
    }

    // ── Delete ───────────────────────────────────────────────────────

    public function test_gestor_can_delete_their_own_orgs_professor_and_the_course_assignments_cascade(): void
    {
        $org = Organization::factory()->create();
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $professor = $this->ownProfessor($org, 'Docente Removível');
        $course = Course::factory()->for($org)->create();
        $course->professors()->attach($professor->id, ['assigned_by' => $gestor->id]);

        $this->assertDatabaseCount('course_professor', 1);

        $response = $this->delete(route('gestor.professors.destroy', $professor));

        $response->assertRedirect(route('gestor.professors.index'));
        $this->assertDatabaseMissing('users', ['id' => $professor->id]);
        $this->assertDatabaseCount('course_professor', 0);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    }

    // ── Route middleware boundaries ──────────────────────────────────

    public function test_an_aluno_is_forbidden_from_the_gestor_professors_routes(): void
    {
        $org = Organization::factory()->create();
        $aluno = User::factory()->create(['org_id' => $org->id]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($aluno);

        $professor = $this->ownProfessor($org);

        $this->get(route('gestor.professors.index'))->assertForbidden();
        $this->get(route('gestor.professors.create'))->assertForbidden();
        $this->get(route('gestor.professors.edit', $professor))->assertForbidden();
    }

    public function test_an_admin_is_forbidden_from_the_gestor_professors_routes(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsAdmin();

        $professor = $this->ownProfessor($org);

        $this->get(route('gestor.professors.index'))->assertForbidden();
        $this->get(route('gestor.professors.create'))->assertForbidden();
        $this->get(route('gestor.professors.edit', $professor))->assertForbidden();
        $this->put(route('gestor.professors.update', $professor), [
            'name' => 'x',
            'email' => 'x@example.com',
        ])->assertForbidden();
        $this->delete(route('gestor.professors.destroy', $professor))->assertForbidden();
    }
}
