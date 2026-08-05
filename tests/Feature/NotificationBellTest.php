<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SPEC-13 §Bucket 2 — the AJAX endpoints backing the topbar notification
 * bell (unread count, mark-all-read, mark-single-read). Every query is
 * implicitly scoped to `$request->user()->notifications()` (the
 * `Notifiable` trait's `MorphMany`), which already guarantees RN12 (no
 * cross-user leak) — no dedicated Policy exists for `DatabaseNotification`.
 */
class NotificationBellTest extends TestCase
{
    /**
     * Inserts a `DatabaseNotification` row directly on the given
     * notifiable, without depending on any concrete `Notification` class
     * (SPEC-13's Bucket 1 domain classes are implemented independently).
     */
    protected function createNotification(User $user, ?string $readAt = null, array $data = []): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => array_merge(['message' => 'Test notification', 'action_url' => '/foo'], $data),
            'read_at' => $readAt,
        ]);
    }

    public function test_unread_count_returns_only_unread_notifications_for_authenticated_user(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');

        $this->createNotification($user);
        $this->createNotification($user);
        $this->createNotification($user, readAt: now()->toDateTimeString());

        $response = $this->getJson(route('notifications.unread-count'));

        $response->assertOk()->assertJson(['count' => 2]);
    }

    public function test_unread_count_is_zero_when_user_has_no_notifications(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');

        $response = $this->getJson(route('notifications.unread-count'));

        $response->assertOk()->assertJson(['count' => 0]);
    }

    public function test_read_all_marks_every_unread_notification_of_the_authenticated_user(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');

        $first = $this->createNotification($user);
        $second = $this->createNotification($user);

        $response = $this->patchJson(route('notifications.read-all'));

        $response->assertOk();
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
    }

    public function test_read_all_does_not_mark_notifications_belonging_to_another_user(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');
        $otherUser = User::factory()->create(['org_id' => $user->org_id]);
        $otherUser->assignRole('aluno');

        $othersNotification = $this->createNotification($otherUser);

        $this->patchJson(route('notifications.read-all'))->assertOk();

        $this->assertNull($othersNotification->fresh()->read_at);
    }

    public function test_read_marks_single_notification_as_read_and_returns_action_url(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');
        $notification = $this->createNotification($user, data: ['action_url' => '/cursos/1/sala-de-aula']);

        $response = $this->patchJson(route('notifications.read', $notification->id));

        $response->assertOk()->assertJson(['action_url' => '/cursos/1/sala-de-aula']);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_returns_404_for_a_notification_id_that_does_not_exist(): void
    {
        $user = $this->actingAsOrgUser(role: 'aluno');

        $response = $this->patchJson(route('notifications.read', (string) Str::uuid()));

        $response->assertNotFound();
    }

    public function test_guest_is_redirected_when_accessing_unread_count(): void
    {
        $response = $this->get(route('notifications.unread-count'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_when_accessing_read_all(): void
    {
        $response = $this->patch(route('notifications.read-all'));

        $response->assertRedirect(route('login'));
    }

    /**
     * RN12 — cross-user isolation: user A cannot mark/read user B's
     * notification even by guessing (or copy/pasting from a browser tab)
     * the target UUID.
     */
    public function test_user_cannot_mark_another_users_notification_as_read_even_by_guessing_uuid(): void
    {
        $userA = $this->actingAsOrgUser(role: 'aluno');
        $organization = $userA->organization;
        $userB = User::factory()->create(['org_id' => $organization->id]);
        $userB->assignRole('aluno');

        $notificationOfB = $this->createNotification($userB);

        $response = $this->patchJson(route('notifications.read', $notificationOfB->id));

        $response->assertNotFound();
        $this->assertNull($notificationOfB->fresh()->read_at);
    }
}
