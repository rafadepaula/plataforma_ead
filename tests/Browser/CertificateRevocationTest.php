<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E coverage: a Gestor revokes a certificate from
 * `courses/{course}/certificates` (`courses.certificates.index`), the
 * `revoke_reason` min:10 validation blocks a too-short reason, and the
 * resulting public verification page immediately reflects the revoked
 * state.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): a
 * jornada de revogação (abrir modal → razão curta bloqueia o envio → razão
 * válida confirma → badge REVOGADO → página pública reflete) acontece na
 * MESMA sessão de modal. A negativa cross-org segue isolada.
 *
 * Certificates are seeded directly using the hash formula documented in
 * `certificates-conventions`.
 */
class CertificateRevocationTest extends DuskTestCase
{
    private function makeCertificate(Course $course, User $user): Certificate
    {
        $issuedAt = Carbon::now();
        $hash = hash('sha256', $user->id.$course->id.$issuedAt->format('Y-m-d H:i:s').config('app.key'));

        return Certificate::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'validation_hash' => $hash,
            'issued_at' => $issuedAt,
        ]);
    }

    public function test_gestor_certificate_revocation_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => null]);
        $certificate = $this->makeCertificate($course, $student);

        $this->browse(function (Browser $browser) use ($gestor, $course, $certificate): void {
            // 1. Abrir o modal de revogação.
            //
            //    Bootstrap boot gate + modal-closed precondition:
            //    `window.bootstrap` is the contract `resources/js/app.js`
            //    publishes once the bundle (and therefore the `data-bs-toggle`
            //    data-api) is evaluated, and a `.modal` without `.show` is the
            //    Bootstrap way of saying "closed".
            $browser->loginAs($gestor)
                ->visit(route('courses.certificates.index', $course))
                ->waitFor('@revoke-certificate-'.$certificate->id)
                ->waitUntil("window.bootstrap !== undefined && !document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show')")
                ->click('@revoke-certificate-'.$certificate->id)
                // `.fade` takes ~150ms: the dialog is already `display:block`
                // (so Dusk's visibility wait passes) while still translating,
                // which is exactly what makes clicks land on the wrong spot.
                ->waitUntil("document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show') && window.getComputedStyle(document.getElementById('revoke-modal-{$certificate->id}')).opacity === '1'")
                ->waitFor('@revoke-reason-'.$certificate->id);

            // 2. Razão com menos de 10 caracteres mantém o envio desabilitado.
            $browser->type('revoke_reason', 'curto')
                ->assertAttribute('@confirm-revoke-'.$certificate->id, 'disabled', 'true');

            $this->assertNull($certificate->fresh()->revoked_at);

            // 3. Razão válida no mesmo modal: envio liberado e confirmado.
            $browser->clear('revoke_reason')
                ->type('revoke_reason', 'Matrícula cancelada retroativamente por fraude.')
                ->waitUntil('!document.querySelector(\'[dusk="confirm-revoke-'.$certificate->id.'"]\').disabled')
                ->click('@confirm-revoke-'.$certificate->id)
                ->waitForLocation('/courses/'.$course->id.'/certificates')
                ->waitFor('@certificate-status-'.$certificate->id)
                // `x-ui.badge` renders with `text-transform: uppercase` CSS —
                // Selenium's `getText()` returns the rendered text, i.e.
                // "REVOGADO", not the DOM's literal "Revogado".
                ->waitForTextInIgnoringCase('@certificate-status-'.$certificate->id, 'Revogado')
                ->assertTextEqualsIgnoringCase('@certificate-status-'.$certificate->id, 'Revogado');

            $this->assertSame(
                'Matrícula cancelada retroativamente por fraude.',
                $certificate->fresh()->revoke_reason
            );
            $this->assertNotNull($certificate->fresh()->revoked_at);

            // 4. Consequência pública: a página de verificação reflete na hora.
            $browser->visit('/validar-certificado/'.$certificate->validation_hash)
                ->waitFor('@certificate-revoked-banner')
                ->assertSeeIn('@certificate-revoke-reason', 'Matrícula cancelada retroativamente por fraude.');
        });
    }
}
