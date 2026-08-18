<?php

namespace Tests\Unit\Services;

use App\Models\Course;
use App\Models\Organization;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * `FileUploadService` stores Lesson media under a per-tenant,
 * per-course isolated path: `orgs/{org_id}/courses/{course_id}/...`,
 * derived from the given `Course`'s own `org_id` (not solely from the
 * currently logged-in user/session), so uploads never land in the wrong
 * tenant's folder.
 */
class FileUploadServiceTest extends TestCase
{
    public function test_store_image_writes_to_the_courses_isolated_org_path(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $service = new FileUploadService;
        $path = $service->storeImage(UploadedFile::fake()->image('capa.png'), $course);

        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/images/", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_store_pdf_writes_to_the_courses_isolated_org_path(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);
        $course = Course::factory()->create(['org_id' => $org->id]);

        $service = new FileUploadService;
        $path = $service->storePdf(UploadedFile::fake()->create('apostila.pdf', 100, 'application/pdf'), $course);

        $this->assertStringStartsWith("orgs/{$org->id}/courses/{$course->id}/pdfs/", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_path_is_derived_from_the_courses_org_id_not_the_acting_admins_impersonated_org(): void
    {
        Storage::fake('public');
        $courseOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $courseOrg->id]);

        // An Admin impersonating a *different* org than the Course's own.
        $this->actingAsAdmin($otherOrg);

        $service = new FileUploadService;
        $path = $service->storeImage(UploadedFile::fake()->image('capa.png'), $course);

        $this->assertStringStartsWith("orgs/{$courseOrg->id}/courses/{$course->id}/images/", $path);
        $this->assertStringNotContainsString("orgs/{$otherOrg->id}/", $path);
    }
}
