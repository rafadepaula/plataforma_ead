<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Course cover (`capa do curso`) management, living under the existing
 * `courses.update` endpoint + `CoursePolicy::update` (no new
 * routes/policies): upload, replacement (old file deleted), explicit
 * removal, validation rejections (`image`, `max:2048`) writing nothing,
 * tenant/role isolation, Admin-impersonation storage path, and the
 * student card row's `coverUrl` exposure.
 */
class CourseCoverManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validAttributes(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Curso com Capa',
            'workload_hours' => 40,
        ], $overrides);
    }

    private function makeAluno(): User
    {
        /** @var User $aluno */
        $aluno = User::factory()->inOrg(Organization::factory()->create())->create();
        $aluno->assignRole(RolesEnum::ALUNO->value);

        return $aluno;
    }

    public function test_gestor_can_upload_a_cover_image_to_a_course(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $response = $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]));

        $response->assertRedirect(route('courses.index'));

        $coverPath = $course->fresh()->cover_path;

        $this->assertIsString($coverPath);
        $this->assertSame("orgs/{$org->id}/courses/{$course->id}/cover", dirname($coverPath));
        Storage::disk('public')->assertExists($coverPath);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'cover_path' => $coverPath]);
    }

    public function test_replacing_a_cover_deletes_the_previous_file(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa-antiga.png'),
        ]))->assertRedirect(route('courses.index'));

        $previousPath = $course->fresh()->cover_path;
        Storage::disk('public')->assertExists($previousPath);

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa-nova.png'),
        ]))->assertRedirect(route('courses.index'));

        $newPath = $course->fresh()->cover_path;

        $this->assertIsString($newPath);
        $this->assertNotSame($previousPath, $newPath);
        Storage::disk('public')->assertMissing($previousPath);
        Storage::disk('public')->assertExists($newPath);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'cover_path' => $newPath]);
    }

    public function test_gestor_can_remove_a_course_cover(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]))->assertRedirect(route('courses.index'));

        $previousPath = $course->fresh()->cover_path;
        Storage::disk('public')->assertExists($previousPath);

        $response = $this->put(route('courses.update', $course), $this->validAttributes([
            'remove_cover' => true,
        ]));

        $response->assertRedirect(route('courses.index'));
        $this->assertNull($course->fresh()->cover_path);
        Storage::disk('public')->assertMissing($previousPath);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'cover_path' => null]);
    }

    public function test_updating_metadata_without_cover_inputs_preserves_the_existing_cover(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]))->assertRedirect(route('courses.index'));

        $coverPath = $course->fresh()->cover_path;

        $this->put(route('courses.update', $course), $this->validAttributes([
            'title' => 'Título Atualizado',
        ]))->assertRedirect(route('courses.index'));

        $this->assertSame($coverPath, $course->fresh()->cover_path);
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_cover_larger_than_two_megabytes_is_rejected_without_writing_anything(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $this->from(route('courses.edit', $course))
            ->put(route('courses.update', $course), $this->validAttributes([
                'cover' => UploadedFile::fake()->create('capa-grande.png', 3000, 'image/png'),
            ]))
            ->assertRedirect(route('courses.edit', $course))
            ->assertSessionHasErrors(['cover']);

        $this->assertNull($course->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_non_image_cover_is_rejected_without_writing_anything(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);
        $course = Course::factory()->inOrg($org->id)->create();

        $this->from(route('courses.edit', $course))
            ->put(route('courses.update', $course), $this->validAttributes([
                'cover' => UploadedFile::fake()->create('capa.pdf', 100, 'application/pdf'),
            ]))
            ->assertRedirect(route('courses.edit', $course))
            ->assertSessionHasErrors(['cover']);

        $this->assertNull($course->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_aluno_cannot_upload_a_cover(): void
    {
        Storage::fake('public');
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $this->actingAsOrgUser($org, RolesEnum::ALUNO->value);

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]))->assertForbidden();

        $this->assertNull($course->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_gestor_from_another_org_cannot_upload_a_cover(): void
    {
        Storage::fake('public');
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->inOrg($otherOrg->id)->create();
        $this->actingAsOrgUser(role: RolesEnum::GESTOR->value);

        // OrgScope hides the row entirely for a Gestor of a different org,
        // so route-model binding itself 404s before authorization runs.
        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]))->assertNotFound();

        $this->assertNull(Course::withoutGlobalScopes()->findOrFail($course->id)->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_admin_impersonating_an_org_stores_the_cover_under_that_orgs_path(): void
    {
        Storage::fake('public');
        $orgB = Organization::factory()->create();
        $course = Course::factory()->inOrg($orgB->id)->create();
        $this->actingAsAdmin($orgB);

        $this->put(route('courses.update', $course), $this->validAttributes([
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]))->assertRedirect(route('courses.index'));

        $coverPath = $course->fresh()->cover_path;

        $this->assertIsString($coverPath);
        $this->assertSame("orgs/{$orgB->id}/courses/{$course->id}/cover", dirname($coverPath));
        Storage::disk('public')->assertExists($coverPath);
    }

    public function test_student_card_row_exposes_the_cover_url(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();
        $course->forceFill([
            'cover_path' => "orgs/{$org->id}/courses/{$course->id}/cover/capa.png",
        ])->save();

        $aluno = $this->makeAluno();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();

        $row = $response->viewData('rows')->first(
            fn (object $row): bool => $row->course->id === $course->id,
        );

        $this->assertNotNull($row);
        $this->assertSame($course->fresh()->cover_url, $row->coverUrl);
    }

    public function test_student_card_row_cover_url_stays_null_without_a_cover(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->inOrg($org->id)->create();

        $aluno = $this->makeAluno();
        $aluno->courses()->attach($course->id, ['status' => 'active', 'enrolled_at' => now()]);

        $response = $this->actingAs($aluno)->get(route('student.courses.index'));

        $response->assertOk();

        $row = $response->viewData('rows')->first(
            fn (object $row): bool => $row->course->id === $course->id,
        );

        $this->assertNotNull($row);
        $this->assertNull($row->coverUrl);
    }
}
