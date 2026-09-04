<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Meus Cursos" do Professor (`professor.courses.index`,
 * `role:professor`): a lista de cursos a ele atribuídos via pivot
 * `course_professor` — `$user->taughtCourses()` é o único filtro, nunca
 * vazando curso de outra Organização. Cada card resume o estado do curso
 * do ponto de vista docente (módulos/lições, alunos ativos, pendências de
 * correção e denúncias pendentes), com link direto para a gestão de
 * conteúdo reutilizada do Gestor.
 */
class ProfessorCourseController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $courses = $user->taughtCourses()
            ->withCount([
                'modules',
                'lessons',
                // `where('course_user.status', ...)` (e não
                // `wherePivot(...)`): dentro de um `withCount()` o
                // `wherePivot` gera `where "pivot" = ...` — coluna que
                // não existe no MySQL (500) e, no SQLite dos testes,
                // compara com literal e zera a contagem em silêncio. Mesmo
                // idiom de `CourseController::index()`.
                'students' => fn (Builder $query): Builder => $query->where('course_user.status', 'active'),
            ])
            ->orderBy('title')
            ->get();

        $courseIds = $courses->pluck('id');

        $pendingEssays = $this->pendingEssaysByCourse($courseIds);
        $pendingReports = $this->pendingReportsByCourse($courseIds);

        return view('professor.courses.index', [
            'courses' => $courses,
            'pendingEssays' => $pendingEssays,
            'pendingReports' => $pendingReports,
        ]);
    }

    /**
     * One grouped query for the whole list — corrections pending per
     * taught course (`awaiting_manual_grading` attempts joined by hand
     * through the cascade `quiz → lesson → module → course`, none of
     * which carry `OrgScope`).
     *
     * @param  Collection<int, int>  $courseIds
     * @return array<int, int>
     */
    protected function pendingEssaysByCourse(Collection $courseIds): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }

        return DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->join('courses', 'courses.id', '=', 'modules.course_id')
            ->whereIn('courses.id', $courseIds)
            ->whereNull('courses.deleted_at')
            ->where('quiz_attempts.status', 'awaiting_manual_grading')
            ->groupBy('courses.id')
            ->selectRaw('courses.id, count(*) as aggregate')
            ->pluck('aggregate', 'courses.id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Pending forum reports per taught course, unioning the two concrete
     * postable tables by hand (`ForumReport` is pseudo-polymorphic, no
     * FK/org column) — same shape as
     * `DashboardMetricsService::pendingForumReportsCount()`.
     *
     * @param  Collection<int, int>  $courseIds
     * @return array<int, int>
     */
    protected function pendingReportsByCourse(Collection $courseIds): array
    {
        if ($courseIds->isEmpty()) {
            return [];
        }

        $topicReports = DB::table('forum_reports')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_reports.postable_id')
            ->whereIn('forum_topics.course_id', $courseIds)
            ->where('forum_reports.postable_type', ForumTopic::class)
            ->where('forum_reports.status', 'pending')
            ->groupBy('forum_topics.course_id')
            ->selectRaw('forum_topics.course_id, count(*) as aggregate');

        $replyReports = DB::table('forum_reports')
            ->join('forum_replies', 'forum_replies.id', '=', 'forum_reports.postable_id')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_replies.topic_id')
            ->whereIn('forum_topics.course_id', $courseIds)
            ->where('forum_reports.postable_type', ForumReply::class)
            ->where('forum_reports.status', 'pending')
            ->groupBy('forum_topics.course_id')
            ->selectRaw('forum_topics.course_id, count(*) as aggregate')
            ->unionAll($topicReports);

        return DB::query()
            ->fromSub($replyReports, 'reports')
            ->groupBy('course_id')
            ->selectRaw('course_id, sum(aggregate) as total')
            ->pluck('total', 'course_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
