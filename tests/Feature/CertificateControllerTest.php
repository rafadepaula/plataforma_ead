<?php

namespace Tests\Feature;

use App\Actions\RevokeCertificateAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * HTTP-layer coverage for `CertificateController`:
 * the Gestor/Admin `index` listing, the `revoke` endpoint (delegating to
 * `RevokeCertificateRequest`/`RevokeCertificateAction`), and the `download`
 * endpoint (delegating to `CertificatePdfService`). `CertificateRevocationTest`
 * already covers the Action/Policy in isolation; this file exercises the
 * routes/controller/Form Request wiring end-to-end.
 */
class CertificateControllerTest extends TestCase
{
    private function certificateFor(Organization $org): Certificate
    {
        $course = Course::factory()->inOrg($org->id)->create();

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        return Certificate::factory()->for($course)->for($student)->create();
    }

    public function test_a_gestor_can_view_the_certificate_list_of_their_own_course(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $response = $this->get(route('courses.certificates.index', $certificate->course_id));

        $response->assertOk();
        $response->assertViewIs('certificates.index');
        $response->assertViewHas('certificates', function ($certificates) use ($certificate) {
            return $certificates->contains('id', $certificate->id);
        });
    }

    public function test_a_gestor_cannot_view_the_certificate_list_of_a_different_orgs_course(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $certificate = $this->certificateFor($otherOrg);
        $this->actingAsOrgUser($ownOrg);

        // `Course`'s own `OrgScope` hides a different org's Course from
        // route-model-binding entirely, so this 404s rather than 403s.
        $this->get(route('courses.certificates.index', $certificate->course_id))
            ->assertNotFound();
    }

    public function test_a_student_cannot_view_the_certificate_list(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        // o painel de certificados é surface de Gestor/Admin: o Aluno,
        // mesmo com o contexto do próprio portal, é barrado pelo role — 403.
        $this->get(route('courses.certificates.index', $certificate->course_id))
            ->assertForbidden();
    }

    public function test_a_gestor_can_revoke_a_certificate_via_http(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $response = $this->post(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Fraude comprovada na avaliação final.',
        ]);

        $response->assertRedirect(route('courses.certificates.index', $certificate->course_id));
        $response->assertSessionHas('success', 'Certificado invalidado com sucesso.');

        $certificate->refresh();
        $this->assertNotNull($certificate->revoked_at);
        $this->assertSame($gestor->id, $certificate->revoked_by);
        $this->assertSame('Fraude comprovada na avaliação final.', $certificate->revoke_reason);
    }

    public function test_revoking_via_http_with_a_reason_shorter_than_10_characters_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'curto',
        ]);

        $response->assertSessionHasErrors('revoke_reason');
        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_a_gestor_cannot_revoke_a_certificate_of_a_different_org_via_http(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $certificate = $this->certificateFor($otherOrg);
        $this->actingAsOrgUser($ownOrg);

        $this->post(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Tentativa indevida de revogação cruzada.',
        ])->assertForbidden();

        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_a_professor_cannot_revoke_or_restore_certificates(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $this->actingAsOrgUser($org, 'professor');

        $this->post(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Professor tentando revogar certificado.',
        ])->assertForbidden();

        $this->post(route('certificates.restore', $certificate))
            ->assertForbidden();

        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_a_gestor_can_restore_a_revoked_certificate_via_http(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Revogação para teste de restauração.');

        $response = $this->post(route('certificates.restore', $certificate));

        $response->assertRedirect(route('courses.certificates.index', $certificate->course_id));
        $response->assertSessionHas('success', 'Certificado validado com sucesso.');

        $certificate->refresh();
        $this->assertNull($certificate->revoked_at);
        $this->assertNull($certificate->revoked_by);
        $this->assertNull($certificate->revoke_reason);
    }

    public function test_an_admin_can_restore_a_revoked_certificate_of_any_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        app(RevokeCertificateAction::class)->execute($certificate, $gestor, 'Revogação para teste de restauração.');

        $this->actingAsAdmin();

        $this->post(route('certificates.restore', $certificate))
            ->assertRedirect(route('courses.certificates.index', $certificate->course_id));

        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_a_gestor_cannot_restore_a_certificate_of_a_different_org_via_http(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->inOrg($otherOrg->id)->create();

        /** @var User $student */
        $student = User::factory()->inOrg($otherOrg->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);

        $certificate = Certificate::factory()->for($course)->for($student)->revoked()->create();
        $this->actingAsOrgUser($ownOrg);

        $this->post(route('certificates.restore', $certificate))
            ->assertForbidden();

        $this->assertNotNull($certificate->fresh()->revoked_at);
    }

    public function test_a_student_cannot_revoke_or_restore_any_certificate(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        $this->post(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Aluno tentando revogar o próprio certificado.',
        ])->assertForbidden();

        $this->post(route('certificates.restore', $certificate))
            ->assertForbidden();

        $this->assertNull($certificate->fresh()->revoked_at);
    }

    public function test_a_gestor_can_download_a_certificate_pdf_of_their_own_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $this->actingAsOrgUser($org);

        $response = $this->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
        $this->assertStringContainsString(
            "certificado-{$certificate->validation_hash}.pdf",
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_an_admin_can_download_a_certificate_pdf_of_any_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $this->actingAsAdmin();

        $response = $this->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith(
            'inline',
            (string) $response->headers->get('Content-Disposition'),
        );
    }

    public function test_a_gestor_cannot_download_a_certificate_pdf_of_a_different_org(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $certificate = $this->certificateFor($otherOrg);
        $this->actingAsOrgUser($ownOrg);

        $this->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }

    public function test_a_student_cannot_download_a_certificate_pdf(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);

        /** @var User $student */
        $student = User::factory()->inOrg($org->id)->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        $this->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }
}
