<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * HTTP-layer coverage for
 * `CourseCompletionRuleController`: creating each of the 3 `rule_type`s,
 * `target_id` cross-field validation (required for
 * `min_quiz_score`/`specific_module`, prohibited for `all_lessons`,
 * must belong to the route-bound Course), removal, and the cross-org 403.
 */
class CourseCompletionRuleTest extends TestCase
{
    private function courseWithModuleAndQuiz(Organization $org): array
    {
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create(['type' => 'quiz']);
        $quiz = Quiz::factory()->for($lesson)->create();

        return [$course, $module, $quiz];
    }

    public function test_gestor_creates_an_all_lessons_rule(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $response->assertRedirect(route('courses.completion-rules.index', $course));
        $this->assertDatabaseHas('course_completion_rules', [
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'target_id' => null,
            'required_percentage' => 100,
        ]);
    }

    public function test_gestor_creates_a_min_quiz_score_rule(): void
    {
        $org = Organization::factory()->create();
        [$course, , $quiz] = $this->courseWithModuleAndQuiz($org);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'min_quiz_score',
            'target_id' => $quiz->id,
            'required_percentage' => 70,
        ]);

        $response->assertRedirect(route('courses.completion-rules.index', $course));
        $this->assertDatabaseHas('course_completion_rules', [
            'course_id' => $course->id,
            'rule_type' => 'min_quiz_score',
            'target_id' => $quiz->id,
            'required_percentage' => 70,
        ]);
    }

    public function test_gestor_creates_a_specific_module_rule(): void
    {
        $org = Organization::factory()->create();
        [$course, $module] = $this->courseWithModuleAndQuiz($org);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'specific_module',
            'target_id' => $module->id,
            'required_percentage' => 100,
        ]);

        $response->assertRedirect(route('courses.completion-rules.index', $course));
        $this->assertDatabaseHas('course_completion_rules', [
            'course_id' => $course->id,
            'rule_type' => 'specific_module',
            'target_id' => $module->id,
            'required_percentage' => 100,
        ]);
    }

    public function test_target_id_is_required_for_min_quiz_score(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'min_quiz_score',
            'required_percentage' => 70,
        ]);

        $response->assertSessionHasErrors('target_id');
        $this->assertDatabaseCount('course_completion_rules', 0);
    }

    public function test_target_id_is_required_for_specific_module(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'specific_module',
            'required_percentage' => 100,
        ]);

        $response->assertSessionHasErrors('target_id');
        $this->assertDatabaseCount('course_completion_rules', 0);
    }

    public function test_target_id_is_prohibited_for_all_lessons(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'target_id' => 1,
            'required_percentage' => 100,
        ]);

        $response->assertSessionHasErrors('target_id');
        $this->assertDatabaseCount('course_completion_rules', 0);
    }

    public function test_target_id_from_a_different_course_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        [, , $otherCourseQuiz] = $this->courseWithModuleAndQuiz($org);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'min_quiz_score',
            'target_id' => $otherCourseQuiz->id,
            'required_percentage' => 70,
        ]);

        $response->assertSessionHasErrors('target_id');
        $this->assertDatabaseCount('course_completion_rules', 0);
    }

    public function test_target_id_module_from_a_different_course_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        [, $otherCourseModule] = $this->courseWithModuleAndQuiz($org);
        $this->actingAsOrgUser($org);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'specific_module',
            'target_id' => $otherCourseModule->id,
            'required_percentage' => 100,
        ]);

        $response->assertSessionHasErrors('target_id');
        $this->assertDatabaseCount('course_completion_rules', 0);
    }

    public function test_gestor_removes_a_rule(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $rule = CourseCompletionRule::factory()->allLessons()->for($course)->create();
        $this->actingAsOrgUser($org);

        $response = $this->delete(route('courses.completion-rules.destroy', [$course, $rule]));

        $response->assertRedirect(route('courses.completion-rules.index', $course));
        $this->assertDatabaseMissing('course_completion_rules', ['id' => $rule->id]);
    }

    /**
     * `Course`'s own `OrgScope` hides a different org's Course entirely
     * from a Gestor's queries, so the route-model-bound `{course}` itself
     * 404s before `CoursePolicy::update()` (and hence a 403) ever runs —
     * same pattern as `EnrollmentManagementTest::
     * test_gestor_from_another_org_cannot_manage_enrollments_of_a_course_they_do_not_own`.
     * An Aluno (same-org, wrong role) is what actually reaches and fails
     * the 403 check, covered by `test_aluno_is_forbidden_from_the_completion_rules_panel`.
     */
    public function test_a_gestor_from_a_different_org_gets_a_404_not_found(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser($ownOrg);

        $response = $this->get(route('courses.completion-rules.index', $course));

        $response->assertNotFound();
    }

    public function test_a_gestor_from_a_different_org_cannot_store_a_rule(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $this->actingAsOrgUser($ownOrg);

        $response = $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $response->assertNotFound();
    }

    public function test_a_gestor_from_a_different_org_cannot_destroy_a_rule(): void
    {
        $ownOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $otherOrg->id]);
        $rule = CourseCompletionRule::factory()->allLessons()->for($course)->create();
        $this->actingAsOrgUser($ownOrg);

        $response = $this->delete(route('courses.completion-rules.destroy', [$course, $rule]));

        $response->assertNotFound();
        $this->assertDatabaseHas('course_completion_rules', ['id' => $rule->id]);
    }

    public function test_aluno_is_forbidden_from_the_completion_rules_panel(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->actingAsOrgUser($org, 'aluno');

        $this->get(route('courses.completion-rules.index', $course))->assertForbidden();
        $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ])->assertForbidden();
    }

    /**
     * Retroactive completion backfill coverage (production bug found
     * 2026-09-05): a student who ALREADY reached the `all_lessons`
     * threshold before the rule existed must be completed and issued a
     * certificate the moment a Gestor creates the rule — the completion
     * pipeline used to be purely lesson-event-driven, so rules created
     * after the fact were never evaluated (ClickUp 86e34v8z6 thread).
     */

    /**
     * @param  iterable<int, Lesson>  $lessons
     * @return array{User, Pivot}
     */
    private function studentAtProgress(Course $course, iterable $lessons, int $completedCount, int $pct): array
    {
        /** @var User $student */
        $student = User::factory()->create(['org_id' => null]);
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, [
            'enrolled_at' => now(),
            'status' => 'active',
            'progress_percentage' => $pct,
        ]);

        foreach (collect($lessons)->slice(0, $completedCount) as $lesson) {
            LessonProgress::create([
                'user_id' => $student->id,
                'lesson_id' => $lesson->id,
                'is_completed' => true,
                'completion_source' => 'manual_click',
                'completed_at' => now(),
            ]);
        }

        $pivot = $course->students()->where('user_id', $student->id)->first()->pivot;

        return [$student, $pivot];
    }

    public function test_creating_a_rule_backfills_completion_for_students_already_at_the_threshold(): void
    {
        Notification::fake();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($module)->create(['is_published' => true]);
        [$student, $pivot] = $this->studentAtProgress($course, $lessons, 2, 100);

        // State before the fix: 100% but never marked completed, no
        // certificate — the rule simply did not exist yet.
        $this->assertNull($pivot->completed_at);
        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $this->actingAsOrgUser($org);
        $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ])->assertRedirect(route('courses.completion-rules.index', $course));

        $this->assertDatabaseHas('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
        $updated = $course->students()->where('user_id', $student->id)->first()->pivot;
        $this->assertSame('completed', $updated->status);
        $this->assertNotNull($updated->completed_at);
    }

    public function test_creating_a_rule_does_not_complete_students_below_the_threshold(): void
    {
        Notification::fake();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($module)->create(['is_published' => true]);
        [$student] = $this->studentAtProgress($course, $lessons, 1, 50);

        $this->actingAsOrgUser($org);
        $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ])->assertRedirect();

        $this->assertDatabaseMissing('certificates', [
            'user_id' => $student->id,
            'course_id' => $course->id,
        ]);
        $pivot = $course->students()->where('user_id', $student->id)->first()->pivot;
        $this->assertSame('active', $pivot->status);
        $this->assertNull($pivot->completed_at);
    }

    public function test_backfill_is_idempotent_for_students_already_completed(): void
    {
        Notification::fake();
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->for($module)->create(['is_published' => true]);
        [$student, $pivot] = $this->studentAtProgress($course, $lessons, 2, 100);

        $certificate = Certificate::factory()->for($course)->for($student)->create();
        $originalCompletedAt = '2026-09-01 10:00:00';
        $course->students()->updateExistingPivot($student->id, [
            'status' => 'completed',
            'completed_at' => $originalCompletedAt,
        ]);
        unset($pivot);

        $this->actingAsOrgUser($org);
        $this->post(route('courses.completion-rules.store', $course), [
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ])->assertRedirect();

        $this->assertSame(
            1,
            Certificate::where('user_id', $student->id)->where('course_id', $course->id)->count(),
            'Backfill must never duplicate an existing certificate.',
        );
        $this->assertSame($certificate->id, Certificate::where('user_id', $student->id)->first()->id);
        $still = $course->students()->where('user_id', $student->id)->first()->pivot;
        $this->assertSame('completed', $still->status);
        $this->assertSame($originalCompletedAt, $still->completed_at->format('Y-m-d H:i:s'));
    }
}
