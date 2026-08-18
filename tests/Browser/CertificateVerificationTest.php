<?php

namespace Tests\Browser;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage of the fully public, unauthenticated
 * `/validar-certificado/{hash}` page (`certificates.verify`): a "Válido"
 * certificate shows student/course/org/workload/issued_at, a "Revogado"
 * one still responds (never a 404) with the revoked banner + reason
 * without hiding the original data, an unknown hash 404s, and no
 * authentication is ever required to reach any of those states.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): os três
 * estados da página pública são percorridos como VISITANTE numa única
 * sessão de navegador; a tela autenticada do Aluno é jornada de outro ator.
 *
 * Certificates are seeded directly here using the
 * `hash('sha256', user_id.course_id.issued_at->format('Y-m-d H:i:s').APP_KEY)`
 * formula documented in the `certificates-conventions` skill.
 */
class CertificateVerificationTest extends DuskTestCase
{
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

    public function test_public_certificate_verification_states_lifecycle(): void
    {
        $org = Organization::factory()->create(['name' => 'Instituto Dusk']);
        $course = Course::factory()->create(['org_id' => $org->id, 'title' => 'Curso Válido Dusk', 'workload_hours' => 40]);
        $student = User::factory()->create(['org_id' => null, 'name' => 'Aluno Válido Dusk']);

        $validCertificate = $this->makeCertificate($course, $student);

        $revokedOrg = Organization::factory()->create(['name' => 'Instituto Revogado Dusk']);
        $revokedCourse = Course::factory()->create(['org_id' => $revokedOrg->id, 'title' => 'Curso Revogado Dusk']);
        $revokedStudent = User::factory()->create(['org_id' => null, 'name' => 'Aluno Revogado Dusk']);
        $revoker = User::factory()->create(['org_id' => $revokedOrg->id]);

        $revokedCertificate = $this->makeCertificate($revokedCourse, $revokedStudent, [
            'revoked_at' => Carbon::now(),
            'revoked_by' => $revoker->id,
            'revoke_reason' => 'Fraude identificada na prova final do curso.',
        ]);

        $this->browse(function (Browser $browser) use ($validCertificate, $revokedCertificate): void {
            // 1. Certificado válido, sem qualquer autenticação.
            $browser->visit('/validar-certificado/'.$validCertificate->validation_hash)
                ->waitFor('@certificate-valid-banner')
                ->assertGuest()
                ->assertSee('Certificado Válido')
                ->assertSeeIn('@certificate-student-name', 'Aluno Válido Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Válido Dusk')
                ->assertSeeIn('@certificate-org-name', 'Instituto Dusk')
                ->assertSeeIn('@certificate-workload', '40h');

            // 2. Certificado revogado: NUNCA 404 — banner + motivo, sem
            //    esconder os dados originais.
            $browser->visit('/validar-certificado/'.$revokedCertificate->validation_hash)
                ->waitFor('@certificate-revoked-banner')
                ->assertSee('Certificado Revogado em')
                ->assertSeeIn('@certificate-revoke-reason', 'Fraude identificada na prova final do curso.')
                ->assertSeeIn('@certificate-student-name', 'Aluno Revogado Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Revogado Dusk');

            // 3. Hash inexistente: 404, e não a página de certificado.
            $browser->visit('/validar-certificado/'.str_repeat('0', 64))
                ->assertSee('404');
        });

        $this->assertDatabaseHas('certificates', [
            'id' => $revokedCertificate->id,
            'revoke_reason' => 'Fraude identificada na prova final do curso.',
        ]);
    }

    /**
     *  a sala de aula do Aluno mostra "Certificado indisponível. X%"
     * quando ainda não existe `Certificate` para o par aluno/curso,
     * reaproveitando exatamente o `course_user.progress_percentage` que a
     * barra de progresso já exibe — nunca um valor recalculado à parte.
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

        $this->assertDatabaseCount('certificates', 0);
    }
}
