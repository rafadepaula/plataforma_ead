<?php

namespace Tests\Browser;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-09 §2 — E2E coverage of the fully public, unauthenticated
 * `/validar-certificado/{hash}` page (`certificates.verify`): a "Válido"
 * certificate shows student/course/org/workload/issued_at, a "Revogado"
 * one still responds (never a 404) with the revoked banner + reason
 * without hiding the original data, and no authentication is ever
 * required to reach either state.
 *
 * Depends on Bucket B's `PublicCertificateController`/routes and Bucket
 * A's `Certificate` write-path being merged; certificates are seeded
 * directly here (no `CertificateFactory` yet — Bucket A) using the
 * `hash('sha256', user_id.course_id.issued_at->format('Y-m-d H:i:s').APP_KEY)`
 * formula documented in the `certificates-conventions` skill.
 */
class CertificateVerificationTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function makeCertificate(Course $course, User $user, array $overrides = []): Certificate
    {
        $issuedAt = $overrides['issued_at'] ?? Carbon::now();

        $hash = hash('sha256', $user->id.$course->id.$issuedAt->format('Y-m-d H:i:s').config('app.key'));

        return Certificate::create(array_merge([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'validation_hash' => $hash,
            'issued_at' => $issuedAt,
        ], $overrides));
    }

    public function test_guest_sees_valid_certificate_data_without_authentication(): void
    {
        $org = Organization::factory()->create(['name' => 'Instituto Dusk']);
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Válido Dusk', 'workload_hours' => 40]);
        $student = User::factory()->create(['org_id' => null, 'name' => 'Aluno Válido Dusk']);

        $certificate = $this->makeCertificate($course, $student);

        $this->browse(function (Browser $browser) use ($certificate): void {
            $browser->visit('/validar-certificado/'.$certificate->validation_hash)
                ->waitFor('@certificate-valid-banner')
                ->assertSee('Certificado Válido')
                ->assertSeeIn('@certificate-student-name', 'Aluno Válido Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Válido Dusk')
                ->assertSeeIn('@certificate-org-name', 'Instituto Dusk')
                ->assertSeeIn('@certificate-workload', '40h');
        });
    }

    public function test_guest_sees_revoked_banner_and_reason_without_a_404(): void
    {
        $org = Organization::factory()->create(['name' => 'Instituto Revogado Dusk']);
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Revogado Dusk']);
        $student = User::factory()->create(['org_id' => null, 'name' => 'Aluno Revogado Dusk']);
        $revoker = User::factory()->create(['org_id' => $org->id]);

        $certificate = $this->makeCertificate($course, $student, [
            'revoked_at' => Carbon::now(),
            'revoked_by' => $revoker->id,
            'revoke_reason' => 'Fraude identificada na prova final do curso.',
        ]);

        $this->browse(function (Browser $browser) use ($certificate): void {
            $browser->visit('/validar-certificado/'.$certificate->validation_hash)
                ->waitFor('@certificate-revoked-banner')
                ->assertSee('Certificado Revogado em')
                ->assertSeeIn('@certificate-revoke-reason', 'Fraude identificada na prova final do curso.')
                ->assertSeeIn('@certificate-student-name', 'Aluno Revogado Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Revogado Dusk');
        });
    }

    public function test_unknown_hash_returns_a_404_not_the_certificate_page(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/validar-certificado/'.str_repeat('0', 64))
                ->assertSee('404');
        });
    }

    /**
     * UC13 — the Aluno's classroom shows "Certificado indisponível. X%"
     * (RN-implied, see `ClassroomController::show()`) when no `Certificate`
     * row exists yet for the student/course pair, reusing the exact same
     * `course_user.progress_percentage` the progress bar already shows —
     * never a separately-computed value.
     */
    public function test_student_without_a_certificate_sees_the_unavailable_banner_with_progress(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Sem Certificado Dusk']);
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole('aluno');

        $course->students()->attach($student->id, [
            'enrolled_at' => Carbon::now(),
            'status' => 'active',
            'progress_percentage' => 45,
        ]);

        $this->browse(function (Browser $browser) use ($student, $course): void {
            $browser->loginAs($student)
                ->visit(route('classroom.show', $course))
                ->waitFor('@certificate-unavailable')
                ->assertSeeIn('@certificate-unavailable', 'Certificado indisponível. 45%')
                ->assertMissing('@download-certificate');
        });
    }
}
