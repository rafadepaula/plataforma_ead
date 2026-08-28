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
        $progressPercentage = (int) ($enrollment?->pivot?->progress_percentage ?? 0);

        $totalLessons = 0;
        $completedLessonsCount = count($completedLessonIds);
        $nextLesson = null;

        foreach ($modules as $module) {
            $moduleTotal = $module->lessons->count();
            $moduleCompleted = $module->lessons->whereIn('id', $completedLessonIds)->count();
            $module->total_lessons_count = $moduleTotal;
            $module->completed_lessons_count = $moduleCompleted;
            $totalLessons += $moduleTotal;

            if (! $nextLesson) {
                foreach ($module->lessons as $lesson) {
                    if (! in_array($lesson->id, $completedLessonIds, true)) {
                        $nextLesson = $lesson;
                        $nextLesson->setRelation('module', $module);
                        break;
                    }
                }
            }
        }

        $certificate = Certificate::query()
            ->where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNull('revoked_at')
            ->first();

        return view('classroom.show', [
            'course' => $course,
            'modules' => $modules,
            'completedLessonIds' => $completedLessonIds,
            'progressPercentage' => $progressPercentage,
            'certificate' => $certificate,
            'nextLesson' => $nextLesson,
            'completedCount' => $completedLessonsCount,
            'completedLessonsCount' => $completedLessonsCount,
            'totalLessons' => $totalLessons,
            'totalLessonsCount' => $totalLessons,
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
