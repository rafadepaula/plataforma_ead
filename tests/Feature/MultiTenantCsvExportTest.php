<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * `GET /admin/reports/{type}/export` (`reports.export`) must
 * genuinely stream (never buffer into an array first, see
 * `CsvStreamExportService`/O(1)-RAM contract in  §1.2): a Gestor's
 * export only ever contains their own Organization's rows, an Admin with
 * no active "Impersonate Org" session gets every Organization's rows, and
 * an Admin impersonating a specific Org gets only that Org's rows. These
 * assertions deliberately check the `StreamedResponse` type/headers
 * rather than fully buffering the response body, so the test itself does
 * not defeat the streaming contract it is verifying.
 */
class MultiTenantCsvExportTest extends TestCase
{
    public function test_gestor_export_streams_only_their_own_orgs_rows(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsOrgUser($orgA, 'gestor');

        $response = $this->get(route('reports.export', ['type' => 'enrollments']));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString($studentA->name, $content);
        $this->assertStringNotContainsString($studentB->name, $content);
    }

    public function test_admin_with_no_impersonated_org_export_streams_every_orgs_rows(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsAdmin();

        $response = $this->get(route('reports.export', ['type' => 'enrollments']));

        $response->assertOk();
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        $content = $response->streamedContent();
        $this->assertStringContainsString($studentA->name, $content);
        $this->assertStringContainsString($studentB->name, $content);
    }

    public function test_admin_impersonating_an_org_export_streams_only_that_orgs_rows(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $courseA = Course::factory()->for($orgA)->create();
        $courseB = Course::factory()->for($orgB)->create();

        $studentA = User::factory()->create(['org_id' => $orgA->id]);
        $studentA->assignRole('aluno');
        $studentB = User::factory()->create(['org_id' => $orgB->id]);
        $studentB->assignRole('aluno');

        $courseA->students()->attach($studentA->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 40,
        ]);
        $courseB->students()->attach($studentB->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => 60,
        ]);

        $this->actingAsAdmin($orgA);

        $response = $this->get(route('reports.export', ['type' => 'enrollments']));

        $response->assertOk();

        $content = $response->streamedContent();
        $this->assertStringContainsString($studentA->name, $content);
        $this->assertStringNotContainsString($studentB->name, $content);
    }

    public function test_gestor_requesting_another_orgs_export_is_forbidden(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $this->actingAsOrgUser($orgA, 'gestor');

        $response = $this->get(route('reports.export', ['type' => 'enrollments', 'org_id' => $orgB->id]));

        $response->assertForbidden();
    }

    public function test_unknown_report_type_returns_not_found(): void
    {
        $org = Organization::factory()->create();

        $this->actingAsOrgUser($org, 'gestor');

        $response = $this->get('/admin/reports/bogus/export');

        $response->assertNotFound();
    }
}
