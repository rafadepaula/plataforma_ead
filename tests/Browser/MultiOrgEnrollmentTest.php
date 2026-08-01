<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * SPEC-06 RF03/RN09 — E2E coverage of the multi-org adaptive invitation
 * flow: a user already registered/enrolled under Organization A visits
 * Organization B's course invitation link, the form collapses to a
 * password-only prompt once their existing e-mail is recognized, and
 * submitting logs them in and enrolls them in Org B's course too — without
 * ever creating a second `users` row.
 */
class MultiOrgEnrollmentTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_an_existing_multi_org_user_can_join_a_second_orgs_course_via_invitation_link(): void
    {
        $orgA = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $student = User::factory()->create([
            'org_id' => $orgA->id,
            'email' => 'multiorg@example.com',
            'password' => Hash::make('senha-correta'),
        ]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);
        $gestorB = User::factory()->create(['org_id' => $orgB->id]);
        $gestorB->assignRole(RolesEnum::GESTOR->value);
        $invitationLink = InvitationLink::factory()->create([
            'org_id' => $orgB->id,
            'course_id' => $courseB->id,
            'created_by' => $gestorB->id,
        ]);

        $this->browse(function (Browser $browser) use ($invitationLink): void {
            $browser->visit('/convite/'.$invitationLink->token)
                ->waitFor('@invitation-form')
                ->assertVisible('@invitation-name')
                ->type('@invitation-email', 'multiorg@example.com')
                ->click('@invitation-name') // blur the e-mail field to trigger the AJAX check
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                ->type('@invitation-password', 'senha-correta')
                ->press('Matricular-me')
                ->waitForLocation('/')
                ->assertAuthenticated();
        });

        $this->assertSame(1, User::where('email', 'multiorg@example.com')->count());

        $student = $student->fresh();
        $this->assertSame($orgA->id, $student->org_id, 'org_id must stay tied to the original Org.');

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $courseB->id,
            'status' => 'active',
        ]);
    }
}
