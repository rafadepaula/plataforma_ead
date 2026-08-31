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
 * authentication is ever required to reach any of those states. Cobre
 * também a entrada sem hash (`/validar-certificado`, o destino do link do
 * rodapé da Landing Page): o formulário de consulta e o `?hash=` que ele
 * submete de volta para a mesma rota.
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

        $this->browse(function (Browser $browser) use ($validCertificate, $revokedCertificate, $student): void {
            // 1. Certificado válido, sem qualquer autenticação — o botão
            //    "Baixar PDF" (gated por @auth na view) não pode vazar para
            //    visitante anônimo, mesmo a rota exigindo staff-ou-dono.
            $browser->visit('/validar-certificado/'.$validCertificate->validation_hash)
                ->waitFor('@certificate-valid-banner')
                ->assertGuest()
                ->assertSee('Certificado Válido')
                ->assertSeeIn('@certificate-student-name', 'Aluno Válido Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Válido Dusk')
                ->assertSeeIn('@certificate-org-name', 'Instituto Dusk')
                ->assertSeeIn('@certificate-workload', '40h')
                ->assertDontSee('Baixar PDF');

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

            // 4. Mesma página, agora autenticado como o dono do certificado:
            //    o botão "Baixar PDF" aparece e aponta para a rota
            //    `certificates.download` (que continua staff-ou-dono no
            //    controller, inalterada por esta tela).
            $browser->loginAs($student)
                ->visit('/validar-certificado/'.$validCertificate->validation_hash)
                ->waitFor('@certificate-valid-banner')
                ->assertSee('Baixar PDF')
                ->assertSourceHas(route('certificates.download', $validCertificate))
                ->logout();
        });

        $this->assertDatabaseHas('certificates', [
            'id' => $revokedCertificate->id,
            'revoke_reason' => 'Fraude identificada na prova final do curso.',
        ]);
    }

    /**
     * Jornada completa da entrada pública sem hash: rodapé da Landing Page →
     * `/validar-certificado` → formulário → hash digitado → página de
     * verificação. É o único produtor do `?hash=` tratado pelo controller,
     * e quem segura o link do rodapé apontando para uma tela útil em vez de
     * um 404.
     */
    public function test_landing_footer_leads_to_the_hash_lookup_form_which_verifies_a_typed_hash(): void
    {
        $org = Organization::factory()->create(['name' => 'Instituto Consulta Dusk']);
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'title' => 'Curso Consulta Dusk',
            'workload_hours' => 20,
        ]);
        $student = User::factory()->create(['org_id' => null, 'name' => 'Aluno Consulta Dusk']);

        $certificate = $this->makeCertificate($course, $student);

        $this->browse(function (Browser $browser) use ($certificate): void {
            $browser->visit('/')
                ->assertGuest()
                ->clickLink('Validar certificado')
                ->waitFor('@certificate-lookup-form')
                ->assertPathIs('/validar-certificado')
                ->assertSee('Validar certificado')
                ->type('@certificate-lookup-hash', $certificate->validation_hash)
                ->click('@certificate-lookup-submit')
                ->waitFor('@certificate-valid-banner')
                ->assertQueryStringHas('hash', $certificate->validation_hash)
                ->assertSeeIn('@certificate-student-name', 'Aluno Consulta Dusk')
                ->assertSeeIn('@certificate-course-title', 'Curso Consulta Dusk')
                ->assertSeeIn('@certificate-org-name', 'Instituto Consulta Dusk');

            // Hash digitado errado: 404, e não o certificado de outra pessoa.
            $browser->visit('/validar-certificado')
                ->waitFor('@certificate-lookup-form')
                ->type('@certificate-lookup-hash', str_repeat('0', 64))
                ->click('@certificate-lookup-submit')
                ->waitForText('404')
                ->assertSee('404');
        });
    }
}
