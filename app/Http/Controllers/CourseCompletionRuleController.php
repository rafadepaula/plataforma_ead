<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseCompletionRuleRequest;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Quiz;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 *  Gestor/Admin panel for managing a Course's
 * `course_completion_rules`, nested under `{course}` (mirrors
 * `EnrollmentController`'s "no dedicated Policy, authorize against the
 * parent Course via `CoursePolicy::update`" convention — see that
 * controller's docblock). Only `index`/`store`/`destroy` are exposed: a
 * rule is simple enough that the Gestor removes and recreates it rather
 * than editing it in place, so there is no `edit`/`update`.
 */
class CourseCompletionRuleController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('update', $course);

        $rules = $course->completionRules()->latest()->get();

        // Options for the `target_id` select when `rule_type` is
        // `min_quiz_score`/`specific_module` — scoped to this Course only,
        // matching `StoreCourseCompletionRuleRequest`'s own
        // belongs-to-course check.
        $modules = $course->modules()->orderBy('order_index')->get(['id', 'title']);
        $quizzes = Quiz::query()
            ->whereHas('lesson.module', fn ($query) => $query->where('course_id', $course->id))
            ->get(['id', 'title']);

        return view('courses.completion-rules.index', [
            'course' => $course,
            'rules' => $rules,
            'modules' => $modules,
            'quizzes' => $quizzes,
        ]);
    }

    public function store(StoreCourseCompletionRuleRequest $request, Course $course): RedirectResponse
    {
        Gate::authorize('update', $course);

        $course->completionRules()->create([
            'rule_type' => $request->validated('rule_type'),
            'target_id' => $request->validated('target_id'),
            'required_percentage' => $request->validated('required_percentage'),
        ]);

        return redirect()->route('courses.completion-rules.index', $course)
            ->with('success', 'Regra de conclusão criada com sucesso.');
    }

    public function destroy(Course $course, CourseCompletionRule $completionRule): RedirectResponse
    {
        Gate::authorize('update', $course);

        abort_unless($completionRule->course_id === $course->id, 404);

        $completionRule->delete();

        return redirect()->route('courses.completion-rules.index', $course)
            ->with('success', 'Regra de conclusão removida com sucesso.');
    }
}
