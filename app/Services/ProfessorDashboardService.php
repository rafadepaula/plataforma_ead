<?php

namespace App\Services;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Métricas do dashboard do Professor (`professor.dashboard`). Sempre
 * parte de `$user->taughtCourses()` (pivot `course_professor`) — nunca de
 * `session('active_org_id')`/`$user->org_id`, semântica do
 * {@see DashboardMetricsService} admin/gestor que aqui NÃO se aplica
 * (o perímetro do Professor é a atribuição por curso, não a Organização).
 */
class ProfessorDashboardService
{
    /**
     * Professores: quantas tentativas a mais entram, tanto faz — a fila é
     * FIFO e o card conta o estado atual, sem janela comparativa.
     *
     * @return array{taught_courses: int, pending_essays: int, pending_reports: int}
     */
    public function statCards(User $user): array
    {
        $courseIds = $user->taughtCourses()->pluck('courses.id');

        return [
            'taught_courses' => $courseIds->count(),
            'pending_essays' => $this->pendingEssaysCount($courseIds),
            'pending_reports' => $this->pendingReportsCount($courseIds),
        ];
    }

    /**
     * "Correções mais antigas": as 5 tentativas mais velhas
     * (`completed_at ASC`, FIFO) dos cursos atribuídos, com aluno e
     * curso/prova resolvidos para o card → link de correção.
     *
     * @return Collection<int, QuizAttempt>
     */
    public function oldestPendingEssays(User $user, int $limit = 5): Collection
    {
        $courseIds = $user->taughtCourses()->pluck('courses.id');

        if ($courseIds->isEmpty()) {
            return collect();
        }

        return QuizAttempt::query()
            ->with(['quiz.lesson.module.course', 'user'])
            ->where('status', 'awaiting_manual_grading')
            ->whereHas('quiz.lesson.module.course', fn ($query) => $query->whereKey($courseIds))
            ->orderBy('completed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Atividade do fórum nos últimos 7 dias: tópicos e respostas novos
     * nos cursos atribuídos, contados em duas queries agrupadas.
     *
     * @return array{topics: int, replies: int}
     */
    public function forumActivity(User $user): array
    {
        $courseIds = $user->taughtCourses()->pluck('courses.id');
        $since = CarbonImmutable::now()->subDays(7);

        if ($courseIds->isEmpty()) {
            return ['topics' => 0, 'replies' => 0];
        }

        return [
            'topics' => ForumTopic::query()
                ->whereIn('course_id', $courseIds)
                ->where('created_at', '>=', $since)
                ->count(),
            'replies' => ForumReply::query()
                ->whereHas('topic', fn ($query) => $query->whereIn('course_id', $courseIds))
                ->where('created_at', '>=', $since)
                ->count(),
        ];
    }

    /**
     * Cards de acesso rápido: os cursos atribuídos, em ordem alfabética.
     *
     * @return Collection<int, Course>
     */
    public function quickAccessCourses(User $user): Collection
    {
        return $user->taughtCourses()
            // `where('course_user.status', ...)` e não `wherePivot(...)`:
            // dentro de `withCount()` o `wherePivot` explode no MySQL
            // (coluna `"pivot"` inexistente) — mesmo idiom de
            // `CourseController::index()`.
            ->withCount([
                'modules',
                'students' => fn (Builder $query): Builder => $query->where('course_user.status', 'active'),
            ])
            ->orderBy('title')
            ->get();
    }

    /**
     * Tentativas aguardando correção nos cursos atribuídos — query
     * manual pelo cascade (`quiz → lesson → module → course`), nenhuma
     * dessas tabelas carrega `OrgScope`.
     *
     * @param  Collection<int, int>  $courseIds
     */
    protected function pendingEssaysCount(Collection $courseIds): int
    {
        if ($courseIds->isEmpty()) {
            return 0;
        }

        return DB::table('quiz_attempts')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
            ->join('modules', 'modules.id', '=', 'lessons.module_id')
            ->join('courses', 'courses.id', '=', 'modules.course_id')
            ->whereIn('courses.id', $courseIds)
            ->whereNull('courses.deleted_at')
            ->where('quiz_attempts.status', 'awaiting_manual_grading')
            ->count();
    }

    /**
     * Denúncias pendentes nos cursos atribuídos — união manual das duas
     * tabelas postable (`ForumReport` é pseudo-polimórfico, sem coluna
     * de org), mesma forma de
     * `DashboardMetricsService::pendingForumReportsCount()`.
     *
     * @param  Collection<int, int>  $courseIds
     */
    protected function pendingReportsCount(Collection $courseIds): int
    {
        if ($courseIds->isEmpty()) {
            return 0;
        }

        $topicReports = DB::table('forum_reports')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_reports.postable_id')
            ->whereIn('forum_topics.course_id', $courseIds)
            ->where('forum_reports.postable_type', ForumTopic::class)
            ->where('forum_reports.status', 'pending')
            ->count();

        $replyReports = DB::table('forum_reports')
            ->join('forum_replies', 'forum_replies.id', '=', 'forum_reports.postable_id')
            ->join('forum_topics', 'forum_topics.id', '=', 'forum_replies.topic_id')
            ->whereIn('forum_topics.course_id', $courseIds)
            ->where('forum_reports.postable_type', ForumReply::class)
            ->where('forum_reports.status', 'pending')
            ->count();

        return $topicReports + $replyReports;
    }
}
