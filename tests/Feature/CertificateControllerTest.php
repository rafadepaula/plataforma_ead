<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * SPEC-09 §1.2 / RF25 — HTTP-layer coverage for `CertificateController`:
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
        $course = Course::factory()->create(['org_id' => $org->id]);

        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
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
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        // A student has no `active_org_id` session context, so `Course`'s
        // own `OrgScope` already fails to resolve the route-bound model
        // before `CoursePolicy::view()` is ever reached — 404, not 403.
        $this->get(route('courses.certificates.index', $certificate->course_id))
            ->assertNotFound();
    }

    public function test_a_gestor_can_revoke_a_certificate_via_http(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $gestor = $this->actingAsOrgUser($org);

        $response = $this->put(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Fraude comprovada na avaliação final.',
        ]);

        $response->assertRedirect(route('courses.certificates.index', $certificate->course_id));
        $response->assertSessionHas('success');

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

        $response = $this->put(route('certificates.revoke', $certificate), [
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

        $this->put(route('certificates.revoke', $certificate), [
            'revoke_reason' => 'Tentativa indevida de revogação cruzada.',
        ])->assertForbidden();

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
    }

    public function test_an_admin_can_download_a_certificate_pdf_of_any_org(): void
    {
        $org = Organization::factory()->create();
        $certificate = $this->certificateFor($org);
        $this->actingAsAdmin();

        $this->get(route('certificates.download', $certificate))
            ->assertOk();
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
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        $this->get(route('certificates.download', $certificate))
            ->assertForbidden();
    }
}
