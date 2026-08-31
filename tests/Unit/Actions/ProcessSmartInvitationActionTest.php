<?php

namespace Tests\Unit\Actions;

use App\Actions\ProcessSmartInvitationAction;
use App\Enums\Permissions\RolesEnum;
use App\Exceptions\InvitationLinkInvalidException;
use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * unit coverage for `ProcessSmartInvitationAction`: the
 * `/convite/{token}` consumption transaction (new-account branch,
 * existing-account branch, link-state guards, and the multi-org
 * no-duplicate-account guarantee).
 */
class ProcessSmartInvitationActionTest extends TestCase
{
    private ProcessSmartInvitationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ProcessSmartInvitationAction;
    }

    private function makeInvitationLink(Organization $org, Course $course, array $attributes = []): InvitationLink
    {
        return InvitationLink::factory()->create(array_merge([
            'org_id' => $org->id,
            'course_id' => $course->id,
            'created_by' => User::factory()->create(['org_id' => $org->id])->id,
        ], $attributes));
    }

    public function test_it_creates_a_new_user_and_enrolls_them_when_the_email_is_new(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);

        $user = $this->action->execute($invitationLink->token, [
            'name' => 'Aluno Novo',
            'email' => 'novo@example.com',
            'cpf' => '12345678900',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'novo@example.com']);
        $this->assertTrue($user->hasRole(RolesEnum::ALUNO->value));
        $this->assertDatabaseHas('course_user', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertSame($org->id, $user->fresh()->org_id);
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());
        $this->assertSame(1, $invitationLink->fresh()->current_uses);
    }

    public function test_it_authenticates_an_existing_user_with_the_correct_password_and_enrolls_them(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);
        $existing = User::factory()->aluno()->create([
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $user = $this->action->execute($invitationLink->token, [
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(1, User::where('email', 'existente@example.com')->count());
        $this->assertDatabaseHas('course_user', [
            'user_id' => $existing->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertSame($existing->id, Auth::id());
    }

    public function test_it_rejects_an_existing_user_with_the_wrong_password_without_creating_an_account(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);
        User::factory()->aluno()->create([
            'email' => 'existente@example.com',
            'password' => 'senha-correta',
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->action->execute($invitationLink->token, [
                'email' => 'existente@example.com',
                'password' => 'senha-errada',
            ]);
        } finally {
            $this->assertFalse(Auth::check());
            $this->assertDatabaseCount('course_user', 0);
            $this->assertSame(0, $invitationLink->fresh()->current_uses);
        }
    }

    public function test_it_rejects_an_expired_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);
        $invitationLink->forceFill(['expires_at' => now()->subDay()])->save();

        try {
            $this->action->execute($invitationLink->token, [
                'name' => 'Aluno',
                'email' => 'aluno@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_EXPIRED, $e->reason());
            $this->assertSame('Este convite expirou.', $e->userMessage());
        }
    }

    public function test_it_rejects_an_exhausted_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course, [
            'max_uses' => 1,
            'current_uses' => 1,
        ]);

        try {
            $this->action->execute($invitationLink->token, [
                'name' => 'Aluno',
                'email' => 'aluno@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_EXHAUSTED, $e->reason());
            $this->assertSame('Limite de vagas atingido.', $e->userMessage());
        }
    }

    public function test_it_rejects_a_revoked_invitation_link(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course, [
            'revoked_at' => now(),
        ]);

        try {
            $this->action->execute($invitationLink->token, [
                'name' => 'Aluno',
                'email' => 'aluno@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_REVOKED, $e->reason());
            $this->assertSame('Este convite foi cancelado.', $e->userMessage());
        }
    }

    public function test_it_rejects_an_unknown_token(): void
    {
        try {
            $this->action->execute('token-inexistente', [
                'name' => 'Aluno',
                'email' => 'aluno@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_NOT_FOUND, $e->reason());
            $this->assertSame('Este convite não foi encontrado.', $e->userMessage());
        }
    }

    /**
     *  a student already registered/enrolled under Org A must not
     * get a second `users` row nor have their original `org_id`
     * overwritten when they use an Org B course's invitation link; they
     * simply gain a second `course_user` row for Org B's course.
     */
    public function test_it_does_not_duplicate_the_account_or_overwrite_org_id_across_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $courseA = Course::factory()->create(['org_id' => $orgA->id, 'is_published' => true]);
        $student = User::factory()->aluno()->create([
            'org_id' => $orgA->id,
            'email' => 'multi-org@example.com',
            'password' => 'senha-correta',
        ]);
        DB::table('course_user')->insert([
            'user_id' => $student->id,
            'course_id' => $courseA->id,
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orgB = Organization::factory()->create();
        $courseB = Course::factory()->create(['org_id' => $orgB->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($orgB, $courseB);

        $user = $this->action->execute($invitationLink->token, [
            'email' => 'multi-org@example.com',
            'password' => 'senha-correta',
        ]);

        $this->assertSame($student->id, $user->id);
        $this->assertSame(1, User::where('email', 'multi-org@example.com')->count());
        $this->assertSame($orgA->id, $user->fresh()->org_id, 'org_id must stay tied to the original Org.');
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

    /**
     * A previously-cancelled enrollment ( revocation) for the same
     * user/course pair must be reactivated rather than blocked by the
     * `UNIQUE(user_id, course_id)` constraint on a second insert attempt.
     */
    public function test_it_reactivates_a_previously_cancelled_enrollment(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $student = User::factory()->aluno()->create([
            'org_id' => $org->id,
            'email' => 'cancelado@example.com',
            'password' => 'senha-correta',
        ]);
        DB::table('course_user')->insert([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'enrolled_at' => now()->subMonth(),
            'status' => 'cancelled',
            'progress_percentage' => 0,
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ]);
        $invitationLink = $this->makeInvitationLink($org, $course);

        $this->action->execute($invitationLink->token, [
            'email' => 'cancelado@example.com',
            'password' => 'senha-correta',
        ]);

        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount('course_user', 1);
    }

    /**
     * Two concurrent requests against a link with exactly one use
     * remaining must not both succeed — the `lockForUpdate` re-check
     * inside the transaction, not just a pre-lock scope filter, is what
     * prevents the second request from over-consuming the link.
     */
    public function test_it_prevents_over_consumption_when_only_one_use_remains(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course, [
            'max_uses' => 1,
            'current_uses' => 0,
        ]);

        $this->action->execute($invitationLink->token, [
            'name' => 'Primeiro',
            'email' => 'primeiro@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame(1, $invitationLink->fresh()->current_uses);

        try {
            $this->action->execute($invitationLink->token, [
                'name' => 'Segundo',
                'email' => 'segundo@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_EXHAUSTED, $e->reason());
            $this->assertSame('Limite de vagas atingido.', $e->userMessage());
        }
    }

    /**
     * The linked Course being unpublished (or soft-deleted) means there is
     * nothing to enroll the invitee into — treated the same as any other
     * unusable-link state, not a silent enrollment into an unavailable
     * Course.
     */
    public function test_it_rejects_a_link_whose_course_is_unpublished(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => false]);
        $invitationLink = $this->makeInvitationLink($org, $course);

        try {
            $this->action->execute($invitationLink->token, [
                'name' => 'Aluno',
                'email' => 'aluno@example.com',
                'password' => 'password123',
            ]);
            $this->fail('Esperava InvitationLinkInvalidException.');
        } catch (InvitationLinkInvalidException $e) {
            $this->assertSame(InvitationLinkInvalidException::REASON_COURSE_UNAVAILABLE, $e->reason());
            $this->assertSame('Este convite não está mais disponível.', $e->userMessage());
        }
    }

    public function test_it_rejects_a_link_whose_course_is_soft_deleted(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);
        $course->delete();

        $this->expectException(InvitationLinkInvalidException::class);

        $this->action->execute($invitationLink->token, [
            'name' => 'Aluno',
            'email' => 'aluno@example.com',
            'password' => 'password123',
        ]);
    }

    /**
     * A staff account (gestor/admin) is not an "aluno" and must not be
     * silently enrolled as a student via the self-service flow — rejected
     * as a form-level error on `email`, distinct from the wrong-password
     * case, and without creating a `course_user` row or consuming the
     * link's `current_uses`.
     */
    public function test_it_rejects_an_existing_staff_account_from_the_self_service_flow(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $invitationLink = $this->makeInvitationLink($org, $course);
        $gestor = User::factory()->create([
            'org_id' => $org->id,
            'email' => 'gestor@example.com',
            'password' => 'senha-correta',
        ]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->expectException(ValidationException::class);

        try {
            $this->action->execute($invitationLink->token, [
                'email' => 'gestor@example.com',
                'password' => 'senha-correta',
            ]);
        } finally {
            $this->assertFalse(Auth::check());
            $this->assertDatabaseCount('course_user', 0);
            $this->assertSame(0, $invitationLink->fresh()->current_uses);
        }
    }
}
