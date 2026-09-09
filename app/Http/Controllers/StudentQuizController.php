<?php

namespace App\Http\Controllers;

use App\Actions\OpenQuizAttemptAction;
use App\Actions\SubmitQuizAttemptAction;
use App\Enums\Permissions\RolesEnum;
use App\Http\Requests\SubmitQuizAttemptRequest;
use App\Models\Lesson;
use App\Models\QuizAttempt;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Controller for the student quiz-taking flow, behind `student.enrolled`
 * and nested under `{lesson}` so enrollment resolution works consistently.
 * The UI is a single-page form — `show()` branches between confirmation,
 * form (`$openAttempt` exists) and blocked states without ever creating an
 * attempt, `start()` stamps the clock (PRG), `submit()` processes the
 * attempt via `SubmitQuizAttemptAction`, and `result()` renders the
 * finalized attempt with the answer key when configured.
 */
class StudentQuizController extends Controller
{
    public function __construct(
        protected SubmitQuizAttemptAction $submitQuizAttemptAction,
        protected OpenQuizAttemptAction $openQuizAttemptAction,
    ) {}

    public function show(Lesson $lesson): View
    {
        $this->abortIfProfessor();

        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
        $lesson->module->setRelation('course', $course);

        $quiz = $lesson->quiz()->with(['questions' => function ($query): void {
            $query->orderBy('order_index')->with('options');
        }])->firstOrFail();

        $user = request()->user();

        /**
         * Uma tentativa aberta e abandonada cujo tempo já se esgotou é
         * encerrada aqui, antes de qualquer contagem: ela vira uma
         * tentativa corrigida (zero, reprovada) e deixa de ser uma linha
         * invisível que impediria o Aluno de recomeçar.
         */
        $expiredAttempt = $this->openQuizAttemptAction->expireStaleAttempt($quiz, $user);

        $completedAttempts = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['awaiting_manual_grading', 'graded'])
            ->count();

        $canAttempt = ($quiz->allow_retries || $completedAttempts === 0)
            && ($quiz->max_attempts === null || $completedAttempts < $quiz->max_attempts);

        $bestScore = $user->bestQuizScoreFor($quiz);

        /**
         * `show()` nunca cria attempt: "prova iniciada" é a existência de
         * uma `in_progress` do usuário (consultada após `expireStaleAttempt()`,
         * para nunca enxergar uma linha zumbi já encerrada). O relógio só é
         * carimbado em `start()` via `openOrResume()`, dono único do
         * `in_progress` — recarregar o form nunca reseta o countdown.
         */
        $openAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest('id')
            ->first();

        $attemptStartedAt = $openAttempt !== null && $quiz->time_limit_minutes
            ? $openAttempt->started_at
            : null;

        $pendingAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_manual_grading')
            ->latest('id')
            ->first();

        $hasPendingGrading = $pendingAttempt !== null;

        $latestGradedAttempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'graded')
            ->with('answers')
            ->latest('id')
            ->first();

        /**
         * Gabarito só quando não há mais o que "vazar": tentativas esgotadas
         * (`!$canAttempt`, estado bloqueado) com ao menos uma tentativa
         * corrigida. Com retentativa disponível, o form/ confirmação nunca
         * recebe o gabarito — ele mora na tela de resultado.
         */
        $showAnswerKey = (bool) ($quiz->show_correct_answers && ! $canAttempt && $latestGradedAttempt !== null);

        return view('student.quizzes.show', [
            'lesson' => $lesson,
            'course' => $course,
            'quiz' => $quiz,
            'canAttempt' => $canAttempt,
            'expiredAttempt' => $expiredAttempt,
            'openAttempt' => $openAttempt,
            'attemptStartedAt' => $attemptStartedAt,
            'completedAttempts' => $completedAttempts,
            'bestScore' => $bestScore,
            'pendingAttempt' => $pendingAttempt,
            'hasPendingGrading' => $hasPendingGrading,
            'showAnswerKey' => $showAnswerKey,
            'latestGradedAttempt' => $latestGradedAttempt,
        ]);
    }

    /**
     * Confirmação explícita de início (PRG): carimba `started_at` via
     * `openOrResume()` — idempotente, dono único do `in_progress`, seguro
     * contra double-click/duas abas — e redireciona ao `show()`, que
     * enxerga o `in_progress` e exibe o form com o relógio preservado.
     */
    public function start(Lesson $lesson): RedirectResponse
    {
        $this->abortIfProfessor();

        $quiz = $lesson->quiz()->firstOrFail();
        $user = request()->user();

        $hasPendingGrading = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'awaiting_manual_grading')
            ->exists();

        if ($hasPendingGrading) {
            return back()->withErrors(['quiz' => 'Sua tentativa anterior ainda aguarda correção manual.']);
        }

        $this->openQuizAttemptAction->openOrResume($quiz, $user);

        return redirect()->route('student.quizzes.show', $lesson);
    }

    public function submit(SubmitQuizAttemptRequest $request, Lesson $lesson): RedirectResponse
    {
        $this->abortIfProfessor();

        /**
         * Guarda contra POST direto com pendência de correção manual: sem
         * ela, `SubmitQuizAttemptAction::execute()` chamaria `openOrResume()`
         * e abriria uma attempt nova sobre a pendente. O Action conta como
         * concluída só `awaiting_manual_grading`/`graded` (nunca `in_progress`),
         * então a checagem aqui — antes de executar — não cria linha alguma.
         */
        $quiz = $lesson->quiz()->firstOrFail();

        $hasPendingGrading = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'awaiting_manual_grading')
            ->exists();

        if ($hasPendingGrading) {
            return back()->withErrors(['quiz' => 'Sua tentativa anterior ainda aguarda correção manual.']);
        }

        $answers = collect($request->validated('answers'))
            ->map(fn (array $answer, string $questionId): array => $answer + ['question_id' => (int) $questionId])
            ->values()
            ->all();

        try {
            $attempt = $this->submitQuizAttemptAction->execute(
                $lesson,
                $request->user(),
                $answers,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        if ($attempt->status === 'awaiting_manual_grading') {
            return redirect()->route('student.quizzes.result', $lesson)
                ->with('success', 'Prova enviada. As questões dissertativas aguardam correção manual.');
        }

        $message = $attempt->is_passed
            ? "Prova concluída com sucesso! Nota: {$attempt->score_percentage}%."
            : "Prova enviada. Nota: {$attempt->score_percentage}%. Você não atingiu a nota mínima.";

        return redirect()->route('student.quizzes.result', $lesson)->with('success', $message);
    }

    /**
     * Tela de resultado pós-envio: exibe a última attempt finalizada
     * (`graded` ou `awaiting_manual_grading`) com gabarito sempre que
     * `show_correct_answers` — a attempt exibida já está finalizada, então
     * não há o que "vazar". Sem attempt a mostrar, volta ao `show()`.
     */
    public function result(Lesson $lesson): View|RedirectResponse
    {
        $this->abortIfProfessor();

        $course = $lesson->module->course()->withoutGlobalScopes()->firstOrFail();
        $lesson->module->setRelation('course', $course);

        $quiz = $lesson->quiz()->with(['questions' => function ($query): void {
            $query->orderBy('order_index')->with('options');
        }])->firstOrFail();

        $user = request()->user();

        $attempt = QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['graded', 'awaiting_manual_grading'])
            ->with('answers')
            ->latest('id')
            ->first();

        if ($attempt === null) {
            return redirect()->route('student.quizzes.show', $lesson);
        }

        $showAnswerKey = (bool) $quiz->show_correct_answers;

        /**
         * "Tempo excedido" computado na leitura a partir de
         * `started_at`/`completed_at`/`time_limit_minutes` — nunca persistido
         * (`quiz_attempts` não tem coluna para isso; ver `quizzes-conventions`).
         */
        $timeExceeded = (bool) ($quiz->time_limit_minutes
            && $attempt->completed_at
            && $attempt->started_at->diffInMinutes($attempt->completed_at) > $quiz->time_limit_minutes);

        return view('student.quizzes.result', [
            'lesson' => $lesson,
            'course' => $course,
            'quiz' => $quiz,
            'attempt' => $attempt,
            'showAnswerKey' => $showAnswerKey,
            'timeExceeded' => $timeExceeded,
        ]);
    }

    /**
     * Fazer prova é exclusivo do Aluno matriculado. Desde que o papel
     * `professor` passou a transitar pelo `student.enrolled` (docência),
     * o middleware sozinho deixaria um Professor atribuído abrir/responder
     * o quiz como se fosse aluno — criando `QuizAttempt` em nome próprio,
     * tentativa que desaguaria na fila de correção que ele mesmo atende.
     * Admin/Gestor seguem liberados (preview, comportamento pré-existente).
     */
    protected function abortIfProfessor(): void
    {
        abort_if(request()->user()?->hasRole(RolesEnum::PROFESSOR->value), 403);
    }
}
