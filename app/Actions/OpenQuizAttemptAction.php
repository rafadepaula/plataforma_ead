<?php

namespace App\Actions;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Single owner of the `in_progress` QuizAttempt lifecycle: the quiz page
 * and the submission both go through here, so there is exactly one
 * definition of what "an attempt is open" means and a single place where
 * the row is created, resumed or expired.
 *
 * An `in_progress` attempt carries no answers and never counts toward
 * `max_attempts` — only `awaiting_manual_grading` and `graded` do. That is
 * exactly why an open attempt is never left behind: `expireStaleAttempt()`
 * closes one whose time limit already ran out, turning an invisible zombie
 * row into a graded (zero, failed) attempt that the student, the Gestor and
 * every counter can see.
 */
class OpenQuizAttemptAction
{
    /**
     * Stamps the server-side start of an attempt and is the single entry
     * point for both the quiz page and the submission: an open attempt
     * that is still within its time limit is always resumed as it is, so
     * reloading the page can never reset the countdown, and the persisted
     * `started_at` — never a client value — drives the time limit at
     * submission. An expired attempt is resumed as it is too: the
     * submission is accepted and `is_passed` is forced to false. When
     * nothing is open (an untimed quiz submitted without ever opening the
     * page, or the very first visit), the attempt is created here.
     *
     * Runs in a single transaction with a row lock. The lock alone cannot
     * carry the invariant — on the first open there is no row to lock —
     * so the `quiz_attempts_open_slot_unique` index is the real guarantee
     * and a losing racer simply resumes the attempt the winner inserted.
     */
    public function openOrResume(Quiz $quiz, User $user): QuizAttempt
    {
        return DB::transaction(function () use ($quiz, $user): QuizAttempt {
            return $this->findOpenAttempt($quiz, $user, lock: true)
                ?? $this->start($quiz, $user);
        });
    }

    /**
     * Closes an open attempt whose time limit already ran out — the
     * student opened the timed quiz and never submitted.
     *
     * The attempt is graded as it stands (no answers, therefore zero) and
     * `completed_at` is pinned to the deadline, so the abandoned row stops
     * being invisible: it counts toward `max_attempts`, shows up for the
     * Gestor and, when retries remain, frees the student to start a fresh
     * attempt. Because expiring costs an attempt, letting the clock run
     * out is never a way to earn a new countdown for free.
     *
     * Returns the expired attempt, or null when there is nothing to expire.
     */
    public function expireStaleAttempt(Quiz $quiz, User $user): ?QuizAttempt
    {
        if (! $quiz->time_limit_minutes) {
            return null;
        }

        return DB::transaction(function () use ($quiz, $user): ?QuizAttempt {
            $openAttempt = $this->findOpenAttempt($quiz, $user, lock: true);

            if ($openAttempt === null || ! $this->hasExpired($quiz, $openAttempt)) {
                return null;
            }

            $openAttempt->update([
                'status' => 'graded',
                'score_percentage' => 0,
                'is_passed' => false,
                'completed_at' => $openAttempt->started_at->copy()->addMinutes($quiz->time_limit_minutes),
            ]);

            return $openAttempt;
        });
    }

    /**
     * The student's currently open (`in_progress`) attempt for this quiz.
     */
    protected function findOpenAttempt(Quiz $quiz, User $user, bool $lock = false): ?QuizAttempt
    {
        return QuizAttempt::query()
            ->where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->latest('id')
            ->first();
    }

    /**
     * True when the quiz is timed and the attempt's time limit has already
     * elapsed.
     */
    protected function hasExpired(Quiz $quiz, QuizAttempt $attempt): bool
    {
        if (! $quiz->time_limit_minutes || ! $attempt->started_at) {
            return false;
        }

        return $attempt->started_at->diffInMinutes(now()) > $quiz->time_limit_minutes;
    }

    protected function start(Quiz $quiz, User $user): QuizAttempt
    {
        try {
            return QuizAttempt::query()->create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->findOpenAttempt($quiz, $user) ?? throw $exception;
        }
    }
}
