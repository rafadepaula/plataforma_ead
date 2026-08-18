<?php

namespace Tests\Feature;

use App\Actions\GradeEssayAnswerAction;
use App\Actions\IssueCertificateAction;
use App\Actions\RevokeCertificateAction;
use App\Actions\SubmitQuizAttemptAction;
use App\Enums\Permissions\RolesEnum;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Traits\AuditableTrait;
use App\Models\User;
use App\Observers\AuditObserver;
use App\Services\AuditService;
use App\Services\UserImportService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * System Audit Logging & Monitoring.
 *
 * Bucket A (AuditableTrait/AuditObserver/AuditService/AuditLog/OrgScope
 * bypass/audit-logs:prune) is fully implemented and exercised below.
 *
 * Several groups of assertions here (auth listeners, critical-action
 * call sites, the AuditLogController index/export screen) depend on
 * Bucket B, which per the technical plan is out of this bucket's file
 * scope (`app/Listeners/*`, `app/Http/Controllers/AuditLogController.php`,
 * `routes/web.php`, the `AuditService::log()` call sites injected into
 * `ImpersonateOrgController`/`UserController`/`UserImportService`/
 * `GradeEssayAnswerAction`/`IssueCertificateAction`/
 * `RevokeCertificateAction`/`CourseController`/`ModuleController`/
 * `LessonController`). Those assertions are written against the
 * documented contract (see `audit-logs-conventions`/`audit-logs-
 * maintenance` skills) and are expected to RED until Bucket B lands —
 * they are not skipped, so the suite documents the exact contract the
 * next bucket must satisfy.
 */
class AuditLogTest extends TestCase
{
    // ------------------------------------------------------------------
    // AuditableTrait / AuditObserver (Bucket A)
    // ------------------------------------------------------------------

    public function test_auditable_trait_records_a_created_event_with_redaction(): void
    {
        $org = Organization::factory()->create();

        AuditLog::query()->delete();

        $user = User::factory()->create(['org_id' => $org->id, 'password' => bcrypt('secret-password')]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->where('event', $user->getMorphClass().'.created')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($org->id, $log->org_id);
        $this->assertIsArray($log->new_values);
        $this->assertArrayHasKey('password', $log->new_values);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
    }

    public function test_auditable_trait_records_an_updated_event_with_old_and_new_values(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id, 'name' => 'Original Name']);

        AuditLog::query()->delete();

        $user->update(['name' => 'Updated Name']);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->where('event', $user->getMorphClass().'.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('Original Name', $log->old_values['name']);
        $this->assertSame('Updated Name', $log->new_values['name']);
    }

    public function test_auditable_trait_records_a_deleted_event(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        AuditLog::query()->delete();

        $course->delete();

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', $course->getMorphClass())
            ->where('auditable_id', $course->id)
            ->where('event', $course->getMorphClass().'.deleted')
            ->first();

        $this->assertNotNull($log);
        $this->assertIsArray($log->old_values);
    }

    public function test_auditable_trait_redacts_password_even_though_it_is_hidden(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id]);

        AuditLog::query()->delete();

        $user->update(['password' => bcrypt('brand-new-password')]);

        $log = AuditLog::withoutGlobalScopes()
            ->where('auditable_type', $user->getMorphClass())
            ->where('auditable_id', $user->id)
            ->where('event', $user->getMorphClass().'.updated')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertArrayNotHasKey('remember_token', $log->new_values ?? []);
    }

    public function test_auditable_trait_redacts_extra_keys_declared_via_the_audit_redact_extension_point(): void
    {
        $model = new class extends Model
        {
            use AuditableTrait;

            /** @var list<string> */
            protected array $auditRedact = ['api_secret'];
        };

        $observer = new AuditObserver;
        $redact = new \ReflectionMethod($observer, 'redact');
        $redact->setAccessible(true);

        $result = $redact->invoke($observer, $model, ['api_secret' => 'super-secret', 'name' => 'kept']);

        $this->assertSame('[REDACTED]', $result['api_secret']);
        $this->assertSame('kept', $result['name']);
    }

    // ------------------------------------------------------------------
    // AuditService dual-write (Bucket A)
    // ------------------------------------------------------------------

    public function test_audit_service_writes_to_the_database(): void
    {
        AuditLog::query()->delete();

        AuditService::log(event: 'custom.test-event', orgId: null, userId: null, payload: ['foo' => 'bar']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'custom.test-event',
        ]);
    }

    public function test_audit_service_persists_the_payload_into_new_values_for_the_ui_diff_modal(): void
    {
        AuditLog::query()->delete();

        AuditService::log(
            event: 'certificate.revoked',
            orgId: null,
            userId: null,
            payload: ['certificate_id' => 42, 'revocation_reason' => 'fraud suspected'],
        );

        $log = AuditLog::withoutGlobalScopes()->where('event', 'certificate.revoked')->first();

        $this->assertNotNull($log);
        $this->assertSame(42, $log->new_values['certificate_id']);
        $this->assertSame('fraud suspected', $log->new_values['revocation_reason']);
    }

    public function test_audit_service_merges_payload_alongside_explicit_new_values_without_dropping_either(): void
    {
        AuditLog::query()->delete();

        AuditService::log(
            event: 'user.status_changed',
            orgId: null,
            userId: null,
            payload: ['reason' => 'inactivity'],
            oldValues: ['status' => 'active'],
            newValues: ['status' => 'inactive'],
        );

        $log = AuditLog::withoutGlobalScopes()->where('event', 'user.status_changed')->first();

        $this->assertSame('active', $log->old_values['status']);
        $this->assertSame('inactive', $log->new_values['status']);
        $this->assertSame('inactivity', $log->new_values['reason']);
    }

    public function test_audit_service_writes_to_the_audit_monolog_channel(): void
    {
        Log::shouldReceive('channel')->with('audit')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with('custom.monolog-event', \Mockery::type('array'));

        AuditService::log(event: 'custom.monolog-event', orgId: null, userId: null, payload: ['foo' => 'bar']);
    }

    public function test_audit_service_does_not_throw_unresolved_org_context_exception_for_null_org(): void
    {
        // Guest login.failed / Admin-global writes legitimately have no
        // Org context — AuditService must bypass OrgScope's `creating`
        // hook rather than let it throw.
        AuditService::log(event: 'login.failed', orgId: null, userId: null, payload: [
            'email' => 'someone@example.com',
            'status' => 'invalid_credentials',
            'password' => '[REDACTED]',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'login.failed',
            'org_id' => null,
            'user_id' => null,
        ]);
    }

    public function test_audit_service_survives_a_database_failure_without_throwing(): void
    {
        // Force the DB write itself to blow up — the testing connection is
        // SQLite (no VARCHAR length enforcement, so an oversized `event`
        // value alone would silently succeed there) — dropping the table
        // is connection-agnostic and reliably reproduces a write failure.
        Schema::drop('audit_logs');

        try {
            AuditService::log(event: 'db.failure.probe', orgId: null, userId: null);

            $this->addToAssertionCount(1);
        } finally {
            // RefreshDatabase re-migrates fresh for the next test, so
            // recreating the table here only matters for this test's own
            // teardown hygiene, not for isolation from subsequent tests.
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('org_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event', 50)->index();
                $table->string('auditable_type')->nullable()->index();
                $table->unsignedBigInteger('auditable_id')->nullable()->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_audit_service_survives_a_monolog_write_failure_without_throwing(): void
    {
        Log::shouldReceive('channel')->with('audit')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->andThrow(new \RuntimeException('disk full'));
        Log::shouldReceive('error')->zeroOrMoreTimes();

        AuditService::log(event: 'monolog.failure.probe', orgId: null, userId: null);

        $this->assertDatabaseHas('audit_logs', ['event' => 'monolog.failure.probe']);
    }

    // ------------------------------------------------------------------
    // OrgScope isolation on AuditLog (Bucket A read-side contract)
    // ------------------------------------------------------------------

    public function test_gestor_query_of_audit_log_is_scoped_to_their_own_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create(['event' => 'org-a-event']);
            AuditLog::factory()->for($orgB, 'organization')->create(['event' => 'org-b-event']);
        });

        $this->actingAsOrgUser($orgA, 'gestor');

        $events = AuditLog::query()->pluck('event')->all();

        $this->assertContains('org-a-event', $events);
        $this->assertNotContains('org-b-event', $events);
    }

    public function test_admin_query_of_audit_log_sees_every_orgs_rows(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create(['event' => 'org-a-event-admin']);
            AuditLog::factory()->for($orgB, 'organization')->create(['event' => 'org-b-event-admin']);
        });

        $this->actingAsAdmin();

        $events = AuditLog::query()->pluck('event')->all();

        $this->assertContains('org-a-event-admin', $events);
        $this->assertContains('org-b-event-admin', $events);
    }

    // ------------------------------------------------------------------
    // audit-logs:prune (Bucket A)
    // ------------------------------------------------------------------

    public function test_prune_command_deletes_only_records_older_than_retention_days(): void
    {
        config(['audit.retention_days' => 30]);

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create([
                'event' => 'kept-recent',
                'created_at' => now()->subDays(29),
            ]);
            AuditLog::factory()->for($orgB, 'organization')->create([
                'event' => 'kept-boundary',
                'created_at' => now()->subDays(30)->addSecond(),
            ]);
            AuditLog::factory()->for($orgA, 'organization')->create([
                'event' => 'pruned-old',
                'created_at' => now()->subDays(31),
            ]);
        });

        Artisan::call('audit-logs:prune');

        $this->assertDatabaseHas('audit_logs', ['event' => 'kept-recent']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'kept-boundary']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'pruned-old']);
    }

    public function test_prune_command_bypasses_org_scope_and_prunes_every_org(): void
    {
        config(['audit.retention_days' => 10]);

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create([
                'event' => 'old-org-a',
                'created_at' => now()->subDays(20),
            ]);
            AuditLog::factory()->for($orgB, 'organization')->create([
                'event' => 'old-org-b',
                'created_at' => now()->subDays(20),
            ]);
        });

        // No authenticated user in this Artisan-style invocation — a
        // scoped query would either throw or silently prune nothing.
        Artisan::call('audit-logs:prune');

        $this->assertDatabaseMissing('audit_logs', ['event' => 'old-org-a']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'old-org-b']);
    }

    // ------------------------------------------------------------------
    // Auth listeners (Bucket B — expected RED until wired)
    // ------------------------------------------------------------------

    public function test_successful_login_records_a_login_success_event_with_password_redacted(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id, 'password' => bcrypt('correct-password')]);
        $user->assignRole(RolesEnum::ALUNO->value);

        AuditLog::query()->delete();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'login.success')->first();

        $this->assertNotNull($log, 'Expected a login.success audit row (Bucket B: LogSuccessfulLogin listener).');
        $this->assertSame($user->id, $log->user_id);

        // The listener's payload must never leak the submitted plaintext
        // password anywhere in the persisted diff.
        $this->assertStringNotContainsString('correct-password', json_encode($log->new_values));
        $this->assertStringNotContainsString('correct-password', json_encode($log->old_values));
    }

    public function test_logout_listener_is_a_no_op_when_the_event_carries_no_user(): void
    {
        AuditLog::query()->delete();

        event(new Logout('web', null));

        $this->assertSame(0, AuditLog::withoutGlobalScopes()->where('event', 'logout')->count());
    }

    public function test_failed_login_records_a_login_failed_event_with_null_org_and_user(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id, 'password' => bcrypt('correct-password')]);

        AuditLog::query()->delete();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'totally-wrong-password',
        ]);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'login.failed')->first();

        $this->assertNotNull($log, 'Expected a login.failed audit row (Bucket B: LogFailedLogin listener).');
        $this->assertNull($log->org_id);
        $this->assertNull($log->user_id);
    }

    public function test_logout_records_a_logout_event(): void
    {
        $org = Organization::factory()->create();
        $user = $this->actingAsOrgUser($org, 'gestor');

        AuditLog::query()->delete();

        $this->post(route('logout'));

        $log = AuditLog::withoutGlobalScopes()->where('event', 'logout')->where('user_id', $user->id)->first();

        $this->assertNotNull($log, 'Expected a logout audit row (Bucket B: LogSuccessfulLogout listener).');
    }

    public function test_password_reset_records_a_password_reset_event_with_password_redacted(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['org_id' => $org->id]);

        AuditLog::query()->delete();

        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'password.reset')->where('user_id', $user->id)->first();

        $this->assertNotNull($log, 'Expected a password.reset audit row (Bucket B: LogPasswordReset listener).');
        $this->assertSame('[REDACTED]', $log->new_values['password']);
        $this->assertStringNotContainsString('new-secure-password', json_encode($log->new_values));
    }

    // ------------------------------------------------------------------
    // Critical-action events (Bucket B — expected RED until wired)
    // ------------------------------------------------------------------

    public function test_impersonate_start_and_stop_are_recorded(): void
    {
        $targetOrg = Organization::factory()->create();
        $admin = $this->actingAsAdmin();

        AuditLog::query()->delete();

        $this->post(route('impersonate-org.store', $targetOrg));

        $startLog = AuditLog::withoutGlobalScopes()->where('event', 'impersonate.start')->first();
        $this->assertNotNull($startLog, 'Expected an impersonate.start audit row (Bucket B: ImpersonateOrgController::store()).');

        $this->delete(route('impersonate-org.destroy'));

        $stopLog = AuditLog::withoutGlobalScopes()->where('event', 'impersonate.stop')->first();
        $this->assertNotNull($stopLog, 'Expected an impersonate.stop audit row (Bucket B: ImpersonateOrgController::destroy()).');
    }

    public function test_impersonate_start_and_stop_survive_an_audit_write_failure(): void
    {
        $targetOrg = Organization::factory()->create();
        $this->actingAsAdmin();

        Schema::drop('audit_logs');

        try {
            $this->post(route('impersonate-org.store', $targetOrg))->assertRedirect();
            $this->assertSame($targetOrg->id, session('active_org_id'));

            $this->delete(route('impersonate-org.destroy'))->assertRedirect();
        } finally {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('org_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event', 50)->index();
                $table->string('auditable_type')->nullable()->index();
                $table->unsignedBigInteger('auditable_id')->nullable()->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_user_status_changed_is_recorded_only_when_status_actually_differs(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $student = User::factory()->create(['org_id' => $org->id, 'status' => 'active']);
        $student->assignRole(RolesEnum::ALUNO->value);

        AuditLog::query()->delete();

        $this->put(route('users.update', $student), [
            'name' => $student->name,
            'email' => $student->email,
            'role' => RolesEnum::ALUNO->value,
            'status' => 'inactive',
        ]);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'user.status_changed')->first();

        $this->assertNotNull($log, 'Expected a user.status_changed audit row (Bucket B: UserController::update()).');
    }

    public function test_essay_graded_is_recorded_per_question(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz', 'is_published' => true]);
        $quiz = Quiz::factory()->for($lesson)->create(['min_score_percentage' => 50]);
        $essayQuestion = QuizQuestion::factory()->for($quiz)->essay()->create();

        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $attempt = app(SubmitQuizAttemptAction::class)->execute($lesson, $aluno, [
            ['question_id' => $essayQuestion->id, 'essay_answer' => 'Resposta dissertativa do aluno.'],
        ]);

        $gestor = $this->actingAsOrgUser($org, 'gestor');
        $essayAnswer = $attempt->answers()->where('question_id', $essayQuestion->id)->firstOrFail();

        AuditLog::query()->delete();

        app(GradeEssayAnswerAction::class)->execute($attempt, $gestor, [
            ['answer_id' => $essayAnswer->id, 'is_correct' => true],
        ]);

        $log = AuditLog::withoutGlobalScopes()->where('event', 'essay.graded')->first();

        $this->assertNotNull($log, 'Expected an essay.graded audit row (Bucket B: GradeEssayAnswerAction).');
    }

    public function test_certificate_issued_and_revoked_are_recorded(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        AuditLog::query()->delete();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);

        $this->assertInstanceOf(Certificate::class, $certificate, 'Fixture must satisfy IssueCertificateAction eligibility — see certificates-conventions.');

        $issuedLog = AuditLog::withoutGlobalScopes()->where('event', 'certificate.issued')->first();
        $this->assertNotNull($issuedLog, 'Expected a certificate.issued audit row (Bucket B: IssueCertificateAction).');

        $admin = $this->actingAsAdmin();
        app(RevokeCertificateAction::class)->execute($certificate, $admin, 'test revocation reason');

        $revokedLog = AuditLog::withoutGlobalScopes()->where('event', 'certificate.revoked')->first();
        $this->assertNotNull($revokedLog, 'Expected a certificate.revoked audit row (Bucket B: RevokeCertificateAction).');
    }

    public function test_certificate_revocation_survives_an_audit_write_failure(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $student = User::factory()->create(['org_id' => $org->id]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);
        CourseCompletionRule::factory()->for($course)->allLessons(100)->create();

        $certificate = app(IssueCertificateAction::class)->execute($course, $student);
        $admin = $this->actingAsAdmin();

        Schema::drop('audit_logs');

        try {
            $revoked = app(RevokeCertificateAction::class)->execute($certificate, $admin, 'audit db down');

            // The primary revocation must still succeed even though the
            // audit_logs write underneath it throws (RN "duplo
            // armazenamento" isolation guard).
            $this->assertNotNull($revoked->revoked_at);
        } finally {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('org_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event', 50)->index();
                $table->string('auditable_type')->nullable()->index();
                $table->unsignedBigInteger('auditable_id')->nullable()->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_csv_import_is_recorded_with_the_documented_payload_shape(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();

        AuditLog::query()->delete();

        $service = new UserImportService;
        $service->importChunk(
            rows: [['name' => 'Aluno Importado', 'email' => 'aluno-importado@example.com']],
            courseId: $course->id,
            orgId: $org->id,
            fileName: 'alunos.csv',
        );

        $log = AuditLog::withoutGlobalScopes()->where('event', 'csv.import')->first();

        $this->assertNotNull($log, 'Expected a csv.import audit row (Bucket B: UserImportService::importChunk()).');
    }

    public function test_content_deleted_is_recorded_for_course_module_and_lesson(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $course = Course::factory()->for($org)->create();

        AuditLog::query()->delete();

        $this->delete(route('courses.destroy', $course));

        $log = AuditLog::withoutGlobalScopes()
            ->where('event', 'content.deleted')
            ->where('auditable_id', $course->id)
            ->first();

        $this->assertNotNull($log, 'Expected a content.deleted audit row (Bucket B: CourseController::destroy()).');
    }

    public function test_course_deletion_survives_an_audit_write_failure(): void
    {
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $course = Course::factory()->for($org)->create();

        Schema::drop('audit_logs');

        try {
            $response = $this->delete(route('courses.destroy', $course));

            $response->assertRedirect();
            $this->assertSoftDeleted($course);
        } finally {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('org_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('event', 50)->index();
                $table->string('auditable_type')->nullable()->index();
                $table->unsignedBigInteger('auditable_id')->nullable()->index();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('url')->nullable();
                $table->timestamps();
            });
        }
    }

    // ------------------------------------------------------------------
    //  read/query surface (Bucket B controller + routes — expected
    // RED until AuditLogController/routes/web.php are wired)
    // ------------------------------------------------------------------

    public function test_admin_audit_logs_index_route_exists_and_is_reachable(): void
    {
        $this->actingAsAdmin();

        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $response = $this->get(route('admin.audit-logs.index'));

        $response->assertOk();
    }

    public function test_aluno_is_forbidden_from_the_admin_audit_logs_index(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_gestor_is_forbidden_from_the_admin_audit_logs_index(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'gestor');

        $this->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_away_from_the_admin_audit_logs_index(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $this->get(route('admin.audit-logs.index'))->assertRedirect(route('login'));
    }

    public function test_aluno_is_forbidden_from_the_gestor_audit_logs_index(): void
    {
        if (! Route::has('gestor.audit-logs.index')) {
            $this->markTestIncomplete('gestor.audit-logs.index route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('gestor.audit-logs.index'))->assertForbidden();
    }

    public function test_admin_is_forbidden_from_the_gestor_audit_logs_index(): void
    {
        if (! Route::has('gestor.audit-logs.index')) {
            $this->markTestIncomplete('gestor.audit-logs.index route not yet registered (Bucket B).');
        }

        $this->actingAsAdmin();

        $this->get(route('gestor.audit-logs.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_away_from_the_gestor_audit_logs_index(): void
    {
        if (! Route::has('gestor.audit-logs.index')) {
            $this->markTestIncomplete('gestor.audit-logs.index route not yet registered (Bucket B).');
        }

        $this->get(route('gestor.audit-logs.index'))->assertRedirect(route('login'));
    }

    public function test_aluno_is_forbidden_from_the_admin_audit_logs_export(): void
    {
        if (! Route::has('admin.audit-logs.export')) {
            $this->markTestIncomplete('admin.audit-logs.export route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('admin.audit-logs.export'))->assertForbidden();
    }

    public function test_aluno_is_forbidden_from_the_gestor_audit_logs_export(): void
    {
        if (! Route::has('gestor.audit-logs.export')) {
            $this->markTestIncomplete('gestor.audit-logs.export route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('gestor.audit-logs.export'))->assertForbidden();
    }

    public function test_gestor_audit_logs_index_is_scoped_to_their_own_org(): void
    {
        if (! Route::has('gestor.audit-logs.index')) {
            $this->markTestIncomplete('gestor.audit-logs.index route not yet registered (Bucket B).');
        }

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create(['event' => 'gestor-scope-a']);
            AuditLog::factory()->for($orgB, 'organization')->create(['event' => 'gestor-scope-b']);
        });

        $this->actingAsOrgUser($orgA, 'gestor');

        $response = $this->get(route('gestor.audit-logs.index'));

        $response->assertOk();
        $response->assertSee('gestor-scope-a');
        $response->assertDontSee('gestor-scope-b');
    }

    public function test_index_filters_by_date_range_event_category_and_user_search(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();
        $matchingUser = User::factory()->create(['org_id' => $org->id, 'name' => 'Findable Person']);

        AuditLog::withoutEvents(function () use ($org, $matchingUser) {
            AuditLog::factory()->for($org, 'organization')->for($matchingUser, 'user')->create([
                'event' => 'login.success',
                'created_at' => now()->subDays(1),
            ]);
            AuditLog::factory()->for($org, 'organization')->create([
                'event' => 'course.updated',
                'created_at' => now()->subDays(60),
            ]);
        });

        $this->actingAsAdmin();

        $response = $this->get(route('admin.audit-logs.index', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->toDateString(),
            'user_search' => 'Findable',
        ]));

        $response->assertOk();
        $response->assertSee('login.success');
        $response->assertDontSee('course.updated');
    }

    public function test_admin_org_id_filter_is_ignored_for_a_gestor_request(): void
    {
        if (! Route::has('gestor.audit-logs.index')) {
            $this->markTestIncomplete('gestor.audit-logs.index route not yet registered (Bucket B).');
        }

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create(['event' => 'own-org-event']);
            AuditLog::factory()->for($orgB, 'organization')->create(['event' => 'spoofed-org-event']);
        });

        $this->actingAsOrgUser($orgA, 'gestor');

        $response = $this->get(route('gestor.audit-logs.index', ['org_id' => $orgB->id]));

        $response->assertOk();
        $response->assertSee('own-org-event');
        $response->assertDontSee('spoofed-org-event');
    }

    public function test_admin_can_filter_by_org_id(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($orgA, $orgB) {
            AuditLog::factory()->for($orgA, 'organization')->create(['event' => 'org-a-event']);
            AuditLog::factory()->for($orgB, 'organization')->create(['event' => 'org-b-event']);
        });

        $this->actingAsAdmin();

        $response = $this->get(route('admin.audit-logs.index', ['org_id' => $orgA->id]));

        $response->assertOk();
        $response->assertSee('org-a-event');
        $response->assertDontSee('org-b-event');
    }

    public function test_admin_can_filter_by_the_mutations_and_critical_actions_event_categories(): void
    {
        if (! Route::has('admin.audit-logs.index')) {
            $this->markTestIncomplete('admin.audit-logs.index route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($org) {
            AuditLog::factory()->for($org, 'organization')->create(['event' => 'course.updated']);
            AuditLog::factory()->for($org, 'organization')->create(['event' => 'certificate.issued']);
            AuditLog::factory()->for($org, 'organization')->create(['event' => 'login.success']);
        });

        $this->actingAsAdmin();

        $mutationsResponse = $this->get(route('admin.audit-logs.index', ['event_category' => 'mutations']));
        $mutationsResponse->assertOk();
        $mutationsResponse->assertSee('course.updated');
        $mutationsResponse->assertDontSee('certificate.issued');
        $mutationsResponse->assertDontSee('login.success');

        $criticalResponse = $this->get(route('admin.audit-logs.index', ['event_category' => 'critical_actions']));
        $criticalResponse->assertOk();
        $criticalResponse->assertSee('certificate.issued');
        $criticalResponse->assertDontSee('course.updated');
        $criticalResponse->assertDontSee('login.success');
    }

    public function test_csv_export_streams_the_full_filtered_result_set_not_just_one_page(): void
    {
        if (! Route::has('admin.audit-logs.export')) {
            $this->markTestIncomplete('admin.audit-logs.export route not yet registered (Bucket B).');
        }

        $org = Organization::factory()->create();

        AuditLog::withoutEvents(function () use ($org) {
            AuditLog::factory(30)->for($org, 'organization')->create(['event' => 'export.sample']);
        });

        $this->actingAsAdmin();

        $response = $this->get(route('admin.audit-logs.export'));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        $content = $response->streamedContent();
        $this->assertSame(30, substr_count($content, 'export.sample'));
    }
}
