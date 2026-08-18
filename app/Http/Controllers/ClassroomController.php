<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * the student-facing classroom: a Course's module/lesson
 * tree with per-lesson completion state (`show`), and the individual
 * lesson player (`showLesson`). Both routes sit behind the
 * `student.enrolled` middleware (see `routes/web.php`) — distinct from
 * `CoursePolicy`/`LessonPolicy`, which gate Admin/Gestor management, not
 * student access, so neither action calls `Gate::authorize()`.
 *
 * `{course}` is resolved `withoutGlobalScopes()` (never a typed `Course
 * $course` implicit binding): an Aluno carries no `org_id` of their own,
 * so `OrgScope`'s query scope would otherwise filter the Course out from
 * under them (mirrors `EnsureStudentIsEnrolled::resolveCourse()`).
 */
class ClassroomController extends Controller
{
    public function show(Request $request, int $course): View
    {
        $user = $request->user();
        $course = Course::query()->withoutGlobalScopes()->findOrFail($course);

        $modules = $course->modules()
            ->with(['lessons' => function ($query): void {
                $query->where('is_published', true)->orderBy('order_index');
            }])
            ->orderBy('order_index')
            ->get();

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $modules->flatMap->lessons->pluck('id'))
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->all();

        $enrollment = $user->courses()->withoutGlobalScopes()->where('courses.id', $course->id)->first();

        //  the "Certificado indisponível. X%" classroom banner:
        // `null` here means the student sees the unavailable-with-progress
        // message; a found row (issued, regardless of `rule_type`s beyond
        // `all_lessons`) means they see the download link instead. Reuses
        // this same `$progressPercentage` for the message — never a
        // separately-computed aggregate of the Course's completion rules
        // (see `IssueCertificateAction`'s docblock: rules are AND'd, not
        // averaged into a percentage of their own).
        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        return view('classroom.show', [
            'course' => $course,
            'modules' => $modules,
            'completedLessonIds' => $completedLessonIds,
            'progressPercentage' => (int) ($enrollment->pivot->progress_percentage ?? 0),
            'certificate' => $certificate,
        ]);
    }

    public function showLesson(Request $request, Lesson $lesson): View
    {
        $user = $request->user();

        // an unpublished/draft Lesson does not exist
        // from the Aluno's perspective (mirrors `show()`'s `is_published`
        // filter on the module's lessons); Admin/Gestor retain preview
        // access for course management purposes.
        if (! $lesson->is_published && $user->hasRole(RolesEnum::ALUNO->value)) {
            abort(404);
        }

        // `{course}` bypasses `OrgScope` (see `show()`'s docblock), then
        // is cached onto the `module` relation so the view's
        // `$lesson->module->course` access does not re-trigger a
        // scoped (and, for an org-less Aluno, empty) query.
        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
        $lesson->module->setRelation('course', $course);

        $progress = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->first();

        return view('classroom.lesson', [
            'lesson' => $lesson,
            'course' => $course,
            'isCompleted' => (bool) ($progress?->is_completed),
            'watchedSeconds' => $progress?->watched_seconds,
        ]);
    }
}
