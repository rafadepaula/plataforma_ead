<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Organization;
use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers the course cover (`cover_path`/`cover_url`): the `cover_url`
 * Attribute accessor resolving through the public disk, and
 * `FileUploadService::storeCover()` writing under the course's own
 * tenant folder.
 */
class CourseCoverTest extends TestCase
{
    public function test_cover_url_is_null_when_cover_path_is_null(): void
    {
        $course = Course::factory()->make(['cover_path' => null]);

        $this->assertNull($course->cover_url);
    }

    public function test_cover_url_returns_the_public_disk_url_when_cover_path_is_set(): void
    {
        Storage::fake('public');

        $coverPath = 'orgs/1/courses/1/cover/capa.png';
        $course = Course::factory()->make(['cover_path' => $coverPath]);

        $this->assertSame(Storage::disk('public')->url($coverPath), $course->cover_url);
    }

    public function test_store_cover_writes_under_the_courses_isolated_cover_path(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org);
        $course = Course::factory()->inOrg($org->id)->create();

        $service = new FileUploadService;
        $path = $service->storeCover(UploadedFile::fake()->image('capa.png'), $course);

        $this->assertSame("orgs/{$org->id}/courses/{$course->id}/cover", dirname($path));
        Storage::disk('public')->assertExists($path);
    }
}
