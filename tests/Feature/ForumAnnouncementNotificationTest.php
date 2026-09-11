<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\ForumAnnouncementNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForumAnnouncementNotificationTest extends TestCase
{
    public function test_professor_creating_topic_notifies_all_active_and_completed_enrolled_students(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create(['is_published' => true]);

        /** @var User $professor */
        $professor = User::factory()->professor()->inOrg($org->id)->create(['name' => 'Prof. Roberto']);
        $course->professors()->attach($professor->id);

        /** @var User $activeStudent */
        $activeStudent = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Ativo']);
        $activeStudent->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($activeStudent->id, ['enrolled_at' => now(), 'status' => 'active']);

        /** @var User $completedStudent */
        $completedStudent = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Concluido']);
        $completedStudent->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($completedStudent->id, ['enrolled_at' => now(), 'status' => 'completed']);

        /** @var User $cancelledStudent */
        $cancelledStudent = User::factory()->inOrg($org->id)->create(['name' => 'Aluno Cancelado']);
        $cancelledStudent->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($cancelledStudent->id, ['enrolled_at' => now(), 'status' => 'cancelled']);

        /** @var User $otherCourseStudent */
        $otherCourseStudent = User::factory()->create(['name' => 'Outro Curso']);
        $otherCourseStudent->assignRole(RolesEnum::ALUNO->value);

        $this->actingAs($professor)->post(route('forum.store', $course), [
            'title' => 'Aviso sobre a prova final',
            'content' => 'Estudem os capitulos 1 a 4.',
        ])->assertRedirect();

        Notification::assertSentTo(
            [$activeStudent, $completedStudent],
            ForumAnnouncementNotification::class,
            function (ForumAnnouncementNotification $notification, array $channels, User $notifiable) use ($course): bool {
                $this->assertSame(['database', 'mail'], $channels);

                $dbData = $notification->toDatabase($notifiable);
                $this->assertStringContainsString('Prof. Roberto', $dbData['message']);
                $this->assertStringContainsString('Aviso sobre a prova final', $dbData['message']);
                $this->assertSame($course->id, $dbData['course_id']);

                $mail = $notification->toMail($notifiable);
                $this->assertSame('Novo anúncio no fórum - '.config('app.name'), $mail->subject);

                return true;
            }
        );

        Notification::assertNotSentTo($professor, ForumAnnouncementNotification::class);
        Notification::assertNotSentTo($cancelledStudent, ForumAnnouncementNotification::class);
        Notification::assertNotSentTo($otherCourseStudent, ForumAnnouncementNotification::class);
    }

    public function test_gestor_or_admin_creating_topic_does_not_dispatch_announcements(): void
    {
        Notification::fake();

        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create(['is_published' => true]);

        /** @var User $gestor */
        $gestor = User::factory()->inOrg($org->id)->create();
        $gestor->assignRole(RolesEnum::GESTOR->value);

        /** @var User $student */
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->actingAs($gestor)->post(route('forum.store', $course), [
            'title' => 'Tópico criado pelo gestor',
            'content' => 'Mensagem de teste.',
        ])->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_professor_can_see_topbar_notification_bell(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create(['is_published' => true]);

        /** @var User $professor */
        $professor = User::factory()->professor()->inOrg($org->id)->create();
        $course->professors()->attach($professor->id);

        $response = $this->actingAs($professor)->get(route('forum.index', $course));

        $response->assertOk();
        $response->assertSee('notifications-bell');
    }
}
