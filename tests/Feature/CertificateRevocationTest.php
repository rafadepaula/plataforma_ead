<?php

namespace Tests\Feature;

use App\Actions\RevokeCertificateAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * SPEC-09 §1.2 — `RevokeCertificateAction`'s write path and
 * `CertificatePolicy::revoke()`'s Gestor-own-org/Admin-any-org
 * authorization boundary. Revocation is always logical: the row is never
 * soft- or hard-deleted, only `revoked_at`/`revoked_by`/`revoke_reason`
 * are set.
 */
class CertificateRevocationTest extends TestCase
{
    private function certificateFor(Organization $org): Certificate
    {
        $course = Course::factory()->create(['org_id' => $org->id]);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        return Certificate::factory()->for($course)->for($student)->create();
    }

    public function test_a_gestor_can_revoke_a_certificate_of_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $this->assertTrue($gestor->can('revoke', $certificate));

        $revoked = app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Fraude comprovada na prova.');

        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame($gestor->id, $revoked->revoked_by);
        $this->assertSame('Fraude comprovada na prova.', $revoked->revoke_reason);
    }

    public function test_a_gestor_cannot_revoke_a_certificate_of_a_different_org(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $certificate = $this->certificateFor($otherOrg);
        $gestor = $this->actingAsOrgUser($ownOrg);

        $this->assertFalse($gestor->can('revoke', $certificate));
    }

    public function test_an_admin_can_revoke_a_certificate_of_any_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $admin = $this->actingAsAdmin();

        $this->assertTrue($admin->can('revoke', $certificate));

        $revoked = app(RevokeCertificateAction::class)->execute($certificate, $admin, 'Matrícula cancelada retroativamente.');

        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame($admin->id, $revoked->revoked_by);
    }

    public function test_revoke_reason_shorter_than_10_characters_is_rejected_by_the_action(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $this->expectException(ValidationException::class);

        app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'curto');
    }

    public function test_revoking_an_already_revoked_certificate_is_guarded(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Primeira revogação válida.');

        $this->expectException(ValidationException::class);

        app(RevokeCertificateAction::class)->execute($certificate->fresh(), $gestor, 'Segunda tentativa de revogação.');
    }

    public function test_a_student_cannot_revoke_any_certificate(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->assertFalse($student->can('revoke', $certificate));
    }

    public function test_revoked_by_relation_resolves_the_revoking_user(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $revoked = app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Motivo válido de revogação.');

        $this->assertTrue($revoked->revokedBy->is($gestor));
    }

    public function test_a_revoked_certificate_row_is_never_soft_or_hard_deleted(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Motivo válido de revogação.');

        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);
    }
}
