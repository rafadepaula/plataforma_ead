<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforces at the database level the invariant the application relies
     * on: a student can have at most one open (`in_progress`) attempt per
     * quiz. A lock alone cannot guarantee it, because on the very first
     * open there is no row to lock, so two concurrent requests can both
     * insert.
     *
     * `open_slot` carries `1` while the attempt is open and `NULL` once it
     * is completed. Both MySQL and SQLite treat `NULL`s as distinct in a
     * unique index, so completed attempts never collide while a second
     * open attempt is rejected.
     */
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->unsignedTinyInteger('open_slot')->nullable()->after('status');
        });

        $this->closeDuplicateOpenAttempts();

        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->unique(['quiz_id', 'user_id', 'open_slot'], 'quiz_attempts_open_slot_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table): void {
            $table->dropUnique('quiz_attempts_open_slot_unique');
            $table->dropColumn('open_slot');
        });
    }

    /**
     * Backfills `open_slot` for existing rows. Where legacy data already
     * holds more than one open attempt for the same quiz and student, only
     * the most recent one stays open; the older ones are closed as expired
     * attempts, which is how they are treated from now on.
     */
    private function closeDuplicateOpenAttempts(): void
    {
        $openAttempts = DB::table('quiz_attempts')
            ->where('status', 'in_progress')
            ->orderBy('id')
            ->get(['id', 'quiz_id', 'user_id', 'started_at']);

        $mostRecentByStudent = [];

        foreach ($openAttempts as $attempt) {
            $mostRecentByStudent[$attempt->quiz_id.':'.$attempt->user_id] = $attempt->id;
        }

        foreach ($openAttempts as $attempt) {
            $isMostRecent = $mostRecentByStudent[$attempt->quiz_id.':'.$attempt->user_id] === $attempt->id;

            DB::table('quiz_attempts')->where('id', $attempt->id)->update($isMostRecent
                ? ['open_slot' => 1]
                : [
                    'status' => 'graded',
                    'score_percentage' => 0,
                    'is_passed' => false,
                    'completed_at' => $attempt->started_at,
                    'open_slot' => null,
                ]);
        }
    }
};
