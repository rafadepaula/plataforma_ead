<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Tests\TestCase;

/**
 * the fully public, unauthenticated, cross-tenant
 * `/validar-certificado/{hash}` route. No `auth`/`role`/tenant scoping
 * applies: any Organization's hash resolves for any anonymous visitor,
 * and a revoked certificate must still respond 200 (never 404) with its
 * revoked banner + reason, per §2's auditability requirement.
 */
class PublicVerificationTest extends TestCase
{
    private function studentFor(Course $course): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);

        return $student;
    }

    public function test_a_valid_certificate_shows_student_course_org_workload_and_issued_at(): void
    {
        $org = Organization::factory()->withCnpj()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'workload_hours' => 40]);
        $student = $this->studentFor($course);

        $certificate = Certificate::factory()->for($course)->for($student)->create([
            'validation_hash' => hash('sha256', 'valid-certificate-hash'),
            'issued_at' => now(),
        ]);

        $response = $this->get(route('certificates.verify', $certificate->validation_hash));

        $response->assertOk();
        $response->assertSee($student->name);
        $response->assertSee($course->title);
        $response->assertSee($org->name);
        $response->assertSee('40');
        $response->assertSee('Válido');
    }

    public function test_a_revoked_certificate_returns_200_with_a_revoked_banner_and_reason_never_404(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentFor($course);

        $certificate = Certificate::factory()->for($course)->for($student)->revoked()->create([
            'validation_hash' => hash('sha256', 'revoked-certificate-hash'),
            'revoke_reason' => 'Fraude comprovada na avaliação final.',
        ]);

        $response = $this->get(route('certificates.verify', $certificate->validation_hash));

        $response->assertOk();
        $response->assertSee('Revogado');
        $response->assertSee('Fraude comprovada na avaliação final.');
        // Original data is never hidden, even once revoked.
        $response->assertSee($student->name);
        $response->assertSee($course->title);
    }

    public function test_an_unknown_hash_returns_404(): void
    {
        $response = $this->get(route('certificates.verify', hash('sha256', 'this-hash-was-never-issued')));

        $response->assertNotFound();
    }

    public function test_the_hash_less_entry_point_renders_the_lookup_form_instead_of_404(): void
    {
        $response = $this->get(route('certificates.verify'));

        $response->assertOk();
        $response->assertSee('certificate-lookup-form', false);
        $response->assertSee('action="'.route('certificates.verify').'"', false);
        $response->assertSee('name="hash"', false);
        $response->assertSee('certificate-lookup-submit', false);
    }

    public function test_a_hash_submitted_by_the_lookup_form_as_a_query_string_renders_the_certificate(): void
    {
        $org = Organization::factory()->withCnpj()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'workload_hours' => 40]);
        $student = $this->studentFor($course);

        $certificate = Certificate::factory()->for($course)->for($student)->create([
            'validation_hash' => hash('sha256', 'query-string-hash'),
            'issued_at' => now(),
        ]);

        // Exactly what the GET form produces: `/validar-certificado?hash=…`.
        $response = $this->get(route('certificates.verify').'?hash='.$certificate->validation_hash);

        $response->assertOk();
        $response->assertSee($student->name);
        $response->assertSee($course->title);
        $response->assertSee($org->name);
        $response->assertSee('Válido');
    }

    public function test_a_revoked_hash_submitted_as_a_query_string_still_renders_never_404s(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->studentFor($course);

        $certificate = Certificate::factory()->for($course)->for($student)->revoked()->create([
            'validation_hash' => hash('sha256', 'revoked-query-string-hash'),
            'revoke_reason' => 'Emitido em duplicidade.',
        ]);

        $response = $this->get(route('certificates.verify').'?hash='.$certificate->validation_hash);

        $response->assertOk();
        $response->assertSee('Revogado');
        $response->assertSee('Emitido em duplicidade.');
    }

    public function test_an_unknown_hash_submitted_as_a_query_string_returns_404(): void
    {
        $response = $this->get(
            route('certificates.verify').'?hash='.hash('sha256', 'typed-wrong-in-the-form'),
        );

        $response->assertNotFound();
    }

    public function test_a_blank_query_string_hash_falls_back_to_the_lookup_form(): void
    {
        // Submitting the form empty (or with whitespace) must not 404 —
        // the visitor gets the form back to try again.
        $this->get(route('certificates.verify').'?hash=')->assertOk()
            ->assertSee('certificate-lookup-form', false);

        $this->get(route('certificates.verify').'?hash=%20%20')->assertOk()
            ->assertSee('certificate-lookup-form', false);
    }

    public function test_verification_works_for_any_org_with_no_authentication_or_tenant_scoping(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $courseB = Course::factory()->create(['org_id' => $orgB->id]);

        $studentA = $this->studentFor($courseA);
        $studentB = $this->studentFor($courseB);

        $certificateA = Certificate::factory()->for($courseA)->for($studentA)->create([
            'validation_hash' => hash('sha256', 'org-a-hash'),
        ]);
        $certificateB = Certificate::factory()->for($courseB)->for($studentB)->create([
            'validation_hash' => hash('sha256', 'org-b-hash'),
        ]);

        // No `actingAs()`/tenant context at all — a fully guest visitor.
        $this->get(route('certificates.verify', $certificateA->validation_hash))
            ->assertOk()->assertSee($orgA->name);

        $this->get(route('certificates.verify', $certificateB->validation_hash))
            ->assertOk()->assertSee($orgB->name);
    }
}
