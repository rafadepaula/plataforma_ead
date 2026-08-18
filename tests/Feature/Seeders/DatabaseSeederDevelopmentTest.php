<?php

namespace Tests\Feature\Seeders;

use App\Models\Certificate;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\InvitationLink;
use Database\Seeders\CertificateSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ForumSeeder;
use Database\Seeders\InvitationSeeder;
use Database\Seeders\NotificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * PHPUnit Feature test verifying that running seeders in
 * development/testing environment creates all expected records across feature
 * entities (InvitationLinks, Certificates, ForumTopics, ForumReplies, DatabaseNotifications)
 * with explicit org_id binding, idempotency, and proper event/mail suppression.
 */
class DatabaseSeederDevelopmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_development_seeding_creates_all_expected_records_and_suppresses_mail_and_events(): void
    {
        Mail::fake();
        Notification::fake();

        $this->seed(DatabaseSeeder::class);

        Mail::assertNothingSent();
        Notification::assertNothingSent();

        // 1. Invitation Links verification
        $this->assertSame(4, InvitationLink::query()->count());
        $activeLink = InvitationLink::query()->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        $this->assertNotNull($activeLink);
        $this->assertNotNull($activeLink->org_id);

        $expiredLink = InvitationLink::query()->where('expires_at', '<=', now())->first();
        $this->assertNotNull($expiredLink);
        $this->assertTrue($expiredLink->isExpired());

        // 2. Certificates verification
        $this->assertSame(4, Certificate::query()->count());
        $validCert = Certificate::query()->whereNull('revoked_at')->first();
        $this->assertNotNull($validCert);
        $this->assertEquals(64, strlen($validCert->validation_hash));

        $revokedCert = Certificate::query()->whereNotNull('revoked_at')->first();
        $this->assertNotNull($revokedCert);
        $this->assertTrue($revokedCert->isRevoked());
        $this->assertNotEmpty($revokedCert->revoke_reason);

        // 3. Forum Topics & Replies verification
        $this->assertSame(4, ForumTopic::query()->count());
        $pinnedTopic = ForumTopic::query()->where('is_pinned', true)->first();
        $this->assertNotNull($pinnedTopic);
        $this->assertNotNull($pinnedTopic->org_id);

        $this->assertSame(4, ForumReply::query()->count());

        // 4. Notifications verification
        $this->assertSame(20, DatabaseNotification::query()->count());
        $unreadNotif = DatabaseNotification::query()->whereNull('read_at')->first();
        $this->assertNotNull($unreadNotif);
    }

    public function test_seeding_is_idempotent_when_executed_multiple_times(): void
    {
        Mail::fake();
        Notification::fake();

        $this->seed(InvitationSeeder::class);
        $this->seed(CertificateSeeder::class);
        $this->seed(ForumSeeder::class);
        $this->seed(NotificationSeeder::class);

        $invitationCount = InvitationLink::query()->count();
        $certificateCount = Certificate::query()->count();
        $topicCount = ForumTopic::query()->count();
        $replyCount = ForumReply::query()->count();
        $notificationCount = DatabaseNotification::query()->count();

        // Re-run seeders to assert 100% idempotency
        $this->seed(InvitationSeeder::class);
        $this->seed(CertificateSeeder::class);
        $this->seed(ForumSeeder::class);
        $this->seed(NotificationSeeder::class);

        $this->assertSame($invitationCount, InvitationLink::query()->count());
        $this->assertSame($certificateCount, Certificate::query()->count());
        $this->assertSame($topicCount, ForumTopic::query()->count());
        $this->assertSame($replyCount, ForumReply::query()->count());
        $this->assertSame($notificationCount, DatabaseNotification::query()->count());
    }
}
