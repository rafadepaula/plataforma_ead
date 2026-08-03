<?php

namespace Tests\Unit\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use App\Services\CsvStreamExportService;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * SPEC-12 — `CsvStreamExportService` streams a CSV export
 * (`response()->streamDownload()` + `fputcsv()`, chunked reads) rather
 * than buffering rows into an array first (SPEC-00 §1.2's 128M
 * shared-hosting O(1)-RAM constraint). Content assertions here render the
 * `StreamedResponse`'s callback directly (`sendContent()` + output
 * buffering) instead of dispatching a real HTTP request, keeping this
 * unit test focused purely on the service's query/CSV-shape contract.
 */
class CsvStreamExportServiceTest extends TestCase
{
    private CsvStreamExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CsvStreamExportService;
    }

    private function renderedCsv(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    public function test_it_returns_a_streamed_response_with_a_csv_attachment_content_disposition(): void
    {
        $response = $this->service->stream('enrollments', null);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_it_rejects_an_unknown_report_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->stream('not-a-real-type', null);
    }

    public function test_enrollments_export_contains_only_the_given_orgs_rows(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        $student = User::factory()->create(['org_id' => $org->id, 'name' => 'Ana Costa']);
        $student->courses()->attach($course->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 55,
        ]);

        $outsider = User::factory()->create(['org_id' => $otherOrg->id, 'name' => 'Fora da Org']);
        $outsider->courses()->attach($otherCourse->id, [
            'status' => 'active',
            'enrolled_at' => now(),
            'progress_percentage' => 10,
        ]);

        $content = $this->renderedCsv($this->service->stream('enrollments', $org->id));

        $this->assertStringContainsString('Ana Costa', $content);
        $this->assertStringNotContainsString('Fora da Org', $content);
    }

    public function test_enrollments_export_contains_every_orgs_rows_when_org_id_is_null(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        $studentA = User::factory()->create(['org_id' => $org->id, 'name' => 'Ana Costa']);
        $studentA->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 55]);

        $studentB = User::factory()->create(['org_id' => $otherOrg->id, 'name' => 'Marcos Silva']);
        $studentB->courses()->attach($otherCourse->id, ['status' => 'active', 'enrolled_at' => now(), 'progress_percentage' => 10]);

        $content = $this->renderedCsv($this->service->stream('enrollments', null));

        $this->assertStringContainsString('Ana Costa', $content);
        $this->assertStringContainsString('Marcos Silva', $content);
    }

    public function test_certificates_export_contains_only_the_given_orgs_rows(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->for($org)->create();
        $otherCourse = Course::factory()->for($otherOrg)->create();

        $student = User::factory()->create(['name' => 'João Pereira']);
        Certificate::factory()->for($course)->for($student)->create();

        $outsider = User::factory()->create(['name' => 'Fora da Org']);
        Certificate::factory()->for($otherCourse)->for($outsider)->create();

        $content = $this->renderedCsv($this->service->stream('certificates', $org->id));

        $this->assertStringContainsString('João Pereira', $content);
        $this->assertStringNotContainsString('Fora da Org', $content);
    }

    public function test_enrollments_export_writes_a_header_row(): void
    {
        $content = $this->renderedCsv($this->service->stream('enrollments', null));

        $this->assertStringContainsString('Aluno', $content);
        $this->assertStringContainsString('Curso', $content);
        $this->assertStringContainsString('Status', $content);
    }
}
