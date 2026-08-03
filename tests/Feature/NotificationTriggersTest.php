<?php

namespace Tests\Feature;

use App\Actions\IssueCertificateAction;
use App\Actions\ProcessSmartInvitationAction;
use App\Enums\Permissions\RolesEnum;
use App\Events\ForumReplyPosted;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\CertificateIssuedNotification;
use App\Notifications\EnrollmentConfirmedNotification;
use App\Notifications\InvitationSentNotification;
use App\Notifications\NewForumReplyNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * SPEC-13 §2 — covers the 4 notification triggers wired in Bucket 1: an
 * `InvitationLink` being created (gatilho 1), a `Certificate` being
 * genuinely issued (gatilho 2), a `ForumReply` being posted (gatilho 3),
 * and a `course_user` row transitioning into `active` (gatilho 4).
 */
class NotificationTriggersTest extends TestCase
{
    public function test_creating_an_invitation_link_notifies_its_creator_by_mail_only(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $gestor = $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $this->post(route('courses.invitation-links.store', $course), [])
            ->assertRedirect(route('courses.invitation-links.index', $course));

        Notification::assertSentOnDemand(
            InvitationSentNotification::class,
            fn (InvitationSentNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === $gestor->email
        );

        // Mail-only trigger per SPEC-13 §2's table — no `database` row for
        // this notification type, and no `User` "invitee" to attach it to.
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_issuing_a_certificate_notifies_the_student_via_mail_and_database_only_on_genuine_issuance(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->enrolledStudent($course, 100);
        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);
        $this->assertNotNull($certificate);

        // Idempotent re-fetch of the already-existing row must never
        // re-notify (a later progress recalculation for an already
        // certified student would otherwise duplicate-email them).
        $reFetched = app(IssueCertificateAction::class)->execute($course, $student);
        $this->assertTrue($certificate->is($reFetched));

        Notification::assertSentTimes(CertificateIssuedNotification::class, 1);
        Notification::assertSentTo(
            $student,
            CertificateIssuedNotification::class,
            function (CertificateIssuedNotification $notification, array $channels, User $notifiable) use ($certificate): bool {
                $data = $notification->toDatabase($notifiable);

                return $data['message'] === 'Seu certificado do curso "'.$certificate->course->title.'" foi emitido.'
                    && $data['action_url'] === route('certificates.verify', $certificate->validation_hash)
                    && $data['certificate_id'] === $certificate->id;
            }
        );
    }

    public function test_a_forum_reply_notifies_the_topic_author_and_prior_repliers_but_not_the_current_replier_and_never_duplicates(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $author = User::factory()->create(['org_id' => $org->id]);
        $priorReplier = User::factory()->create(['org_id' => $org->id]);
        $currentReplier = User::factory()->create(['org_id' => $org->id]);

        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $org->id]);
        ForumReply::factory()->for($topic, 'topic')->for($priorReplier)->create();

        // The topic author also replied earlier — they must still receive
        // exactly one notification, not two, for the new reply below.
        ForumReply::factory()->for($topic, 'topic')->for($author)->create();

        $newReply = ForumReply::factory()->for($topic, 'topic')->for($currentReplier)->create();

        event(new ForumReplyPosted($newReply));

        Notification::assertSentTo(
            $author,
            NewForumReplyNotification::class,
            function (NewForumReplyNotification $notification, array $channels, User $notifiable) use ($newReply, $topic): bool {
                $data = $notification->toDatabase($notifiable);

                return $data['message'] === $newReply->user->name.' respondeu ao tópico "'.$topic->title.'".'
                    && $data['action_url'] === route('forum.show', [$topic->course_id, $topic->id])
                    && $data['reply_id'] === $newReply->id;
            }
        );
        Notification::assertSentTo($priorReplier, NewForumReplyNotification::class);
        Notification::assertSentTimes(NewForumReplyNotification::class, 2);
        Notification::assertNotSentTo($currentReplier, NewForumReplyNotification::class);
    }

    /**
     * The self-notification guard (`->reject($reply->user_id)` in
     * `SendNewForumReplyNotifications`) is only meaningfully exercised
     * when the replier already has a prior reply in the same topic —
     * otherwise they were never in the recipient candidate set to begin
     * with, and the guard could regress unnoticed.
     */
    public function test_a_user_replying_twice_to_the_same_topic_is_never_self_notified_on_their_second_reply(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $author = User::factory()->create(['org_id' => $org->id]);
        $repeatReplier = User::factory()->create(['org_id' => $org->id]);

        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $org->id]);
        ForumReply::factory()->for($topic, 'topic')->for($repeatReplier)->create();

        $secondReply = ForumReply::factory()->for($topic, 'topic')->for($repeatReplier)->create();

        event(new ForumReplyPosted($secondReply));

        Notification::assertSentTo($author, NewForumReplyNotification::class);
        Notification::assertSentTimes(NewForumReplyNotification::class, 1);
        Notification::assertNotSentTo($repeatReplier, NewForumReplyNotification::class);
    }

    public function test_gestor_manually_enrolling_a_student_notifies_them_of_the_new_enrollment(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);

        $this->post(route('courses.enrollments.store', $course), ['user_id' => $student->id])
            ->assertRedirect(route('courses.enrollments.index', $course));

        Notification::assertSentTo(
            $student,
            EnrollmentConfirmedNotification::class,
            function (EnrollmentConfirmedNotification $notification, array $channels, User $notifiable) use ($course): bool {
                $data = $notification->toDatabase($notifiable);

                return $data['message'] === 'Sua matrícula no curso "'.$course->title.'" foi confirmada.'
                    && $data['action_url'] === route('classroom.show', $course)
                    && $data['course_id'] === $course->id;
            }
        );
    }

    public function test_reactivating_a_cancelled_enrollment_notifies_the_student_again(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now()->subMonth(), 'status' => 'cancelled']);

        $this->post(route('courses.enrollments.store', $course), ['user_id' => $student->id])
            ->assertRedirect(route('courses.enrollments.index', $course));

        Notification::assertSentTo($student, EnrollmentConfirmedNotification::class);
    }

    public function test_self_service_invitation_enrollment_notifies_the_student(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $creator = User::factory()->create(['org_id' => $org->id]);
        $invitationLink = InvitationLink::factory()->for($course)->create([
            'org_id' => $org->id,
            'created_by' => $creator->id,
        ]);

        $user = app(ProcessSmartInvitationAction::class)->execute($invitationLink->token, [
            'name' => 'Novo Aluno',
            'email' => 'novo.aluno@example.com',
            'password' => 'password123',
        ]);

        Notification::assertSentTo($user, EnrollmentConfirmedNotification::class);
    }

    /**
     * SPEC-13 §3/RN — a mail transport failure must never roll back (or
     * even fail) the business action that triggered the notification: the
     * `->notify()` call site is wrapped in try/catch and logged, so the
     * caller's own persisted state is unaffected regardless of the mail
     * channel's outcome. Exercised through the real HTTP endpoint with a
     * real, persisted student, asserting the `course_user` row it created
     * actually survives — only the notification dispatch (`Notification`
     * facade's `send()`) is faked, never the `User` model under test.
     */
    public function test_a_mail_delivery_failure_does_not_roll_back_the_enrollment_it_was_triggered_by(): void
    {
        Log::shouldReceive('error')->once();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP indisponível'));

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);

        // The listener must swallow the notify() exception rather than let
        // it bubble up and abort the request/transaction.
        $this->post(route('courses.enrollments.store', $course), ['user_id' => $student->id])
            ->assertRedirect(route('courses.enrollments.index', $course));

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $student->id,
            'status' => 'active',
        ]);
    }

    public function test_a_mail_delivery_failure_does_not_prevent_the_certificate_from_being_returned(): void
    {
        Log::shouldReceive('error')->once();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $student = $this->enrolledStudent($course, 100);
        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP indisponível'));

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertNotNull($certificate);
        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
    }

    /**
     * RN12 — a Forum reply's recipient resolution must never cross an
     * Org boundary: only users participating in this exact (single-org)
     * topic are notified.
     */
    public function test_forum_reply_notifications_never_leak_to_a_user_from_another_org(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $author = User::factory()->create(['org_id' => $org->id]);
        $currentReplier = User::factory()->create(['org_id' => $org->id]);

        $otherOrg = Organization::factory()->create();
        $outsider = User::factory()->create(['org_id' => $otherOrg->id]);

        $topic = ForumTopic::factory()->for($course)->for($author)->create(['org_id' => $org->id]);
        $newReply = ForumReply::factory()->for($topic, 'topic')->for($currentReplier)->create();

        event(new ForumReplyPosted($newReply));

        Notification::assertSentTo($author, NewForumReplyNotification::class);
        Notification::assertNotSentTo($outsider, NewForumReplyNotification::class);
    }

    private function enrolledStudent(Course $course, int $progressPercentage = 100): User
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'completed',
            'progress_percentage' => $progressPercentage,
        ]);

        return $student;
    }
}
