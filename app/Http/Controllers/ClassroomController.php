<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ClassroomController extends Controller
{
    /**
     * the classroom overview of a Course the acting user is enrolled in
     * (or previewing as Admin/Gestor).
     *
     * The view contract is frozen and normalized — `course`, `modules`,
     * `progressPercentage`, `completedLessonsCount`, `totalLessonsCount`,
     * `certificate`, `nextLesson`. Per-lesson state travels ON the Lesson
     * models (`is_completed`, `glyph`) so the Blade layer never performs a
     * lookup or resolves media itself.
     *
     * `{course}` is bound as a raw int and read `withoutGlobalScopes()` ON
     * PURPOSE: a multi-org Aluno (`users.org_id` null) has no resolvable org
     * context, so `OrgScope` would throw `UnresolvedOrgContextException`
     * instead of returning the Course. Tenant safety here is the
     * `student.enrolled` middleware (`EnsureStudentIsEnrolled`), which is the
     * real gate — do NOT "fix" this into a route-model-bound `Course`.
     */
    public function show(Request $request, int $course): View
    {
        $user = $request->user();
        $course = Course::query()->withoutGlobalScopes()->findOrFail($course);

        $modules = $course->modules()
            ->with([
                'lessons' => function ($query): void {
                    $query->where('is_published', true)->orderBy('order_index');
                },
                'lessons.media',
            ])
            ->orderBy('order_index')
            ->get();

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('lesson_id', $modules->flatMap->lessons->pluck('id'))
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->all();

        $completedLookup = array_flip($completedLessonIds);

        $enrollment = $user->courses()->withoutGlobalScopes()->where('courses.id', $course->id)->first();

        /** Progress is READ-ONLY from the pivot; never recompute it here or in JS. */
        $progressPercentage = (int) ($enrollment?->pivot?->progress_percentage ?? 0);

        $totalLessonsCount = 0;
        $completedLessonsCount = count($completedLessonIds);
        $nextLesson = null;

        foreach ($modules as $module) {
            $moduleCompleted = 0;

            foreach ($module->lessons as $lesson) {
                $isCompleted = isset($completedLookup[$lesson->id]);

                $lesson->is_completed = $isCompleted;
                $lesson->glyph = $isCompleted ? 'check' : $lesson->pending_glyph;

                if ($isCompleted) {
                    $moduleCompleted++;

                    continue;
                }

                if (! $nextLesson) {
                    $nextLesson = $lesson;
                    $nextLesson->setRelation('module', $module);
                }
            }

            $module->total_lessons_count = $module->lessons->count();
            $module->completed_lessons_count = $moduleCompleted;
            $totalLessonsCount += $module->lessons->count();
        }

        /**
         * Resolved WITHOUT a `revoked_at` filter: revocation is a logical,
         * terminal state and the record must stay resolvable, so the view can
         * tell "revoked" apart from "never issued" and word its neutral
         * unavailable surface accordingly.
         */
        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->first();

        return view('classroom.show', [
            'course' => $course,
            'modules' => $modules,
            'progressPercentage' => $progressPercentage,
            'completedLessonsCount' => $completedLessonsCount,
            'totalLessonsCount' => $totalLessonsCount,
            'certificate' => $certificate,
            'nextLesson' => $nextLesson,
        ]);
    }

    public function showLesson(Request $request, Lesson $lesson): View
    {
        $user = $request->user();

        if (! $lesson->is_published && $user->hasRole(RolesEnum::ALUNO->value)) {
            abort(404);
        }

        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
        $lesson->module->setRelation('course', $course);
        $lesson->loadMissing(['quiz', 'media']);

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
