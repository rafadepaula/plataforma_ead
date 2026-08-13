<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-09 §1.2/RF25 — E2E coverage: a Gestor revokes a certificate from
 * `courses/{course}/certificates` (`courses.certificates.index`), the
 * `revoke_reason` min:10 validation blocks a too-short reason, and the
 * resulting public verification page immediately reflects the revoked
 * state.
 *
 * Depends on Bucket B's `CertificateController@revoke` route/Request and
 * Bucket A's `RevokeCertificateAction`/`CertificatePolicy` being merged;
 * certificates are seeded directly (no `CertificateFactory` yet — Bucket
 * A) using the hash formula documented in `certificates-conventions`.
 */
class CertificateRevocationTest extends DuskTestCase
{
    use DatabaseMigrations;

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

    public function test_gestor_revokes_a_certificate_via_the_ui_and_the_public_page_reflects_it(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => null]);
        $certificate = $this->makeCertificate($course, $student);

        $this->browse(function (Browser $browser) use ($gestor, $course, $certificate): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.certificates.index', $course))
                ->waitFor('@revoke-certificate-'.$certificate->id)
                // Bootstrap boot gate + modal-closed precondition: `window.bootstrap`
                // is the contract `resources/js/app.js` publishes once the bundle
                // (and therefore the `data-bs-toggle` data-api) is evaluated, and a
                // `.modal` without `.show` is the Bootstrap way of saying "closed"
                // (the legacy `.dialog-backdrop` ancestor no longer exists).
                ->waitUntil("window.bootstrap !== undefined && !document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show')")
                ->click('@revoke-certificate-'.$certificate->id)
                // `.fade` takes ~150ms: the dialog is already `display:block` (so
                // Dusk's visibility wait passes) while still translating, which is
                // exactly what makes clicks land on the wrong spot. Wait for the
                // transition to actually finish before touching the form.
                ->waitUntil("document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show') && window.getComputedStyle(document.getElementById('revoke-modal-{$certificate->id}')).opacity === '1'")
                ->waitFor('@revoke-reason-'.$certificate->id)
                ->type('revoke_reason', 'Matrícula cancelada retroativamente por fraude.')
                ->waitUntil('!document.querySelector(\'[dusk="confirm-revoke-'.$certificate->id.'"]\').disabled')
                ->click('@confirm-revoke-'.$certificate->id)
                ->waitForLocation('/courses/'.$course->id.'/certificates')
                ->waitFor('@certificate-status-'.$certificate->id)
                // `x-ui.badge` renders with `text-transform: uppercase`
                // CSS — Selenium's `getText()` returns the rendered
                // (CSS-transformed) text, i.e. "REVOGADO", not the DOM's
                // literal "Revogado" — assert against what's actually
                // displayed rather than the source casing.
                ->waitForTextIn('@certificate-status-'.$certificate->id, 'REVOGADO')
                ->assertSeeIn('@certificate-status-'.$certificate->id, 'REVOGADO');
        });

        $this->assertSame(
            'Matrícula cancelada retroativamente por fraude.',
            $certificate->fresh()->revoke_reason
        );
        $this->assertNotNull($certificate->fresh()->revoked_at);

        $this->browse(function (Browser $browser) use ($certificate): void {
            $browser->visit('/validar-certificado/'.$certificate->validation_hash)
                ->waitFor('@certificate-revoked-banner')
                ->assertSeeIn('@certificate-revoke-reason', 'Matrícula cancelada retroativamente por fraude.');
        });
    }

    public function test_revoke_reason_under_ten_characters_keeps_the_submit_button_disabled(): void
    {
        $org = Organization::factory()->create();
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => null]);
        $certificate = $this->makeCertificate($course, $student);

        $this->browse(function (Browser $browser) use ($gestor, $course, $certificate): void {
            $browser->loginAs($gestor)
                ->visit(route('courses.certificates.index', $course))
                ->waitFor('@revoke-certificate-'.$certificate->id)
                // Same Bootstrap boot gate + modal-closed precondition as above.
                ->waitUntil("window.bootstrap !== undefined && !document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show')")
                ->click('@revoke-certificate-'.$certificate->id)
                // Wait out the `.fade` transition before typing/asserting.
                ->waitUntil("document.getElementById('revoke-modal-{$certificate->id}').classList.contains('show') && window.getComputedStyle(document.getElementById('revoke-modal-{$certificate->id}')).opacity === '1'")
                ->waitFor('@revoke-reason-'.$certificate->id)
                ->type('revoke_reason', 'curto')
                ->assertAttribute('@confirm-revoke-'.$certificate->id, 'disabled', 'true');
        });

        $this->assertNull($certificate->fresh()->revoked_at);
    }

    /**
     * `Course`'s own `OrgScope` (not `CertificatePolicy`) is what blocks
     * this: a Gestor from another Org can never even route-model-bind a
     * foreign Course, so this 404s rather than 403s — mirroring every
     * other `courses/{course}/...` staff screen (see `courses-conventions`).
     */
    public function test_gestor_from_another_org_cannot_reach_the_certificate_list(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $gestor = User::factory()->create(['org_id' => $otherOrg->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $course = Course::factory()->create(['org_id' => $ownOrg->id]);

        $this->browse(function (Browser $browser) use ($gestor, $course): void {
            $browser->loginAs($gestor)
                ->visit('/courses/'.$course->id.'/certificates')
                ->assertSee('404');
        });
    }
}
