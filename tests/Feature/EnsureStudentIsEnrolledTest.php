<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * `EnsureStudentIsEnrolled` gates the student-facing
 * classroom/lesson/progress routes. Bucket 2 wires the real routes; this
 * test registers ad-hoc routes behind the `student.enrolled` alias to
 * exercise the middleware in isolation against its fixed contract.
 */
class EnsureStudentIsEnrolledTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'student.enrolled'])->group(function (): void {
            Route::get('_test/courses/{course}/probe', fn (int $course) => response('ok'));
            Route::get('_test/lessons/{lesson}/probe', fn (int $lesson) => response('ok'));
        });
    }

    private function courseWithLesson(Organization $org): array
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);

        return [$course, $lesson];
    }

    public function test_admin_is_always_allowed(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $this->actingAsAdmin();

        $this->get("_test/courses/{$course->id}/probe")->assertOk();
    }

    public function test_gestor_from_the_same_org_is_allowed(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $this->actingAsOrgUser($org, RolesEnum::GESTOR->value);

        $this->get("_test/courses/{$course->id}/probe")->assertOk();
    }

    public function test_gestor_from_a_different_org_is_forbidden(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $this->actingAsOrgUser($otherOrg, RolesEnum::GESTOR->value);

        $this->get("_test/courses/{$course->id}/probe")->assertForbidden();
    }

    public function test_aluno_with_active_enrollment_is_allowed(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $this->actingAs($student);

        $this->get("_test/courses/{$course->id}/probe")->assertOk();
    }

    public function test_aluno_with_completed_enrollment_is_allowed(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'completed']);
        $this->actingAs($student);

        $this->get("_test/courses/{$course->id}/probe")->assertOk();
    }

    public function test_aluno_with_cancelled_enrollment_is_forbidden(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'cancelled']);
        $this->actingAs($student);

        $this->get("_test/courses/{$course->id}/probe")->assertForbidden();
    }

    public function test_aluno_with_no_enrollment_row_at_all_is_forbidden(): void
    {
        $org = Organization::factory()->create();
        [$course] = $this->courseWithLesson($org);
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $this->actingAs($student);

        $this->get("_test/courses/{$course->id}/probe")->assertForbidden();
    }

    public function test_resolves_course_from_the_lesson_route_parameter(): void
    {
        $org = Organization::factory()->create();
        [$course, $lesson] = $this->courseWithLesson($org);
        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);
        $this->actingAs($student);

        $this->get("_test/lessons/{$lesson->id}/probe")->assertOk();
    }
}
