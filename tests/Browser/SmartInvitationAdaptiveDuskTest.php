<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class SmartInvitationAdaptiveDuskTest extends DuskTestCase
{
    private function createInvitation(Organization $org, Course $course, string $state = 'valid'): InvitationLink
    {
        $gestor = User::factory()->create(['org_id' => $org->id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $factory = InvitationLink::factory();
        $factory = match ($state) {
            'expired' => $factory->expired(),
            'revoked' => $factory->revoked(),
            'exhausted' => $factory->exhausted(),
            default => $factory,
        };

        return $factory->create([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => $gestor->id,
        ]);
    }

    public function test_new_student_registration_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create([
            'org_id' => $org->id,
            'is_published' => true,
        ]);
        $invitation = $this->createInvitation($org, $course);

        $this->browse(function (Browser $browser) use ($invitation): void {
            // 1. Visit invitation link and verify initial expanded form state
            $browser->visit('/convite/'.$invitation->token)
                ->waitFor('@invitation-form')
                ->assertVisible('@invitation-email')
                ->assertVisible('@invitation-name')
                ->assertVisible('@invitation-cpf')
                ->assertVisible('@invitation-password')
                ->assertVisible('@invitation-password-confirmation')
                ->assertDontSee('Já encontramos uma conta com este e-mail');

            // 2. Type new unregistered email and verify fields stay visible
            $browser->type('@invitation-email', 'novo.aluno@example.com')
                ->click('@invitation-name')
                ->pause(500)
                ->assertVisible('@invitation-name')
                ->assertVisible('@invitation-cpf')
                ->assertVisible('@invitation-password-confirmation')
                ->assertDontSee('Já encontramos uma conta com este e-mail');

            // 3. Fill required fields, accept consent switch, and submit
            $browser->type('@invitation-name', 'Aluno Novo Teste')
                ->type('@invitation-cpf', '123.456.789-09')
                ->type('@invitation-password', 'senha12345')
                ->type('@invitation-password-confirmation', 'senha12345')
                ->check('input[name=consent]')
                ->press('@invitation-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        // 4. Assert database records created correctly
        $newUser = User::where('email', 'novo.aluno@example.com')->firstOrFail();
        $this->assertSame($org->id, $newUser->org_id);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $newUser->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('invitation_links', [
            'id' => $invitation->id,
            'current_uses' => 1,
        ]);
    }

    public function test_existing_student_adaptive_linking_lifecycle(): void
    {
        $orgA = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $orgA->id]);
        $existingStudent = User::factory()->create([
            'org_id' => $orgA->id,
            'email' => 'aluno.existente@example.com',
            'password' => Hash::make('senha-existente-123'),
        ]);
        $existingStudent->assignRole(RolesEnum::ALUNO->value);
        $courseA->students()->attach($existingStudent->id, ['enrolled_at' => now(), 'status' => 'active']);

        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create([
            'org_id' => $orgB->id,
            'is_published' => true,
        ]);
        $invitation = $this->createInvitation($orgB, $courseB);

        $this->browse(function (Browser $browser) use ($invitation): void {
            // 1. Visit invitation link and enter existing student email to trigger adaptive collapse
            $browser->visit('/convite/'.$invitation->token)
                ->waitFor('@invitation-form')
                ->assertVisible('@invitation-name')
                ->type('@invitation-email', 'aluno.existente@example.com')
                ->click('@invitation-name')
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                ->waitUntilMissing('@invitation-cpf')
                ->waitUntilMissing('@invitation-password-confirmation')
                ->assertVisible('@invitation-password');

            // 2. Submit with wrong password to verify rejection
            $browser->pause(500)
                ->type('@invitation-password', 'senha-errada')
                ->check('input[name=consent]')
                ->press('@invitation-submit')
                ->waitForText('Senha incorreta para o e-mail informado.')
                ->assertGuest();

            // 3. Re-enter password correctly and submit to enroll
            $browser->waitFor('@invitation-form')
                ->type('@invitation-email', 'aluno.existente@example.com')
                ->click('@invitation-name')
                ->waitFor('@invitation-existing-account-hint')
                ->waitUntilMissing('@invitation-name')
                ->pause(500)
                ->type('@invitation-password', 'senha-existente-123')
                ->check('input[name=consent]')
                ->press('@invitation-submit')
                ->waitForLocation('/meus-cursos')
                ->assertAuthenticated();
        });

        // 4. Assert user was not duplicated and is enrolled in both courses
        $this->assertSame(1, User::where('email', 'aluno.existente@example.com')->count());
        $this->assertSame($orgA->id, $existingStudent->fresh()->org_id);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $existingStudent->id,
            'course_id' => $courseA->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('course_user', [
            'user_id' => $existingStudent->id,
            'course_id' => $courseB->id,
            'status' => 'active',
        ]);
    }

    public function test_invalid_and_expired_invitation_tokens_show_empty_state(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);

        $expired = $this->createInvitation($org, $course, 'expired');
        $revoked = $this->createInvitation($org, $course, 'revoked');

        $message = 'Este link de convite é inválido, expirou ou já atingiu o limite de usos.';

        $this->browse(function (Browser $browser) use ($expired, $revoked, $message): void {
            // 1. Expired invitation token
            $browser->visit('/convite/'.$expired->token)
                ->assertSee($message)
                ->assertMissing('@invitation-form');

            // 2. Revoked invitation token
            $browser->visit('/convite/'.$revoked->token)
                ->assertSee($message)
                ->assertMissing('@invitation-form');

            // 3. Completely non-existent token
            $browser->visit('/convite/token-inexistente-12345')
                ->assertSee($message)
                ->assertMissing('@invitation-form');
        });

        $this->assertDatabaseCount('course_user', 0);
    }
}
