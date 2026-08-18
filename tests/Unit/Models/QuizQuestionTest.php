<?php

namespace Tests\Unit\Models;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use Tests\TestCase;

/**
 * `QuizQuestion` is cascade-inherited (no `OrgScope`, see
 * `quizzes-architecture` skill); this covers its relationships directly.
 */
class QuizQuestionTest extends TestCase
{
    public function test_it_has_an_answers_relationship(): void
    {
        $course = Course::factory()->create(['org_id' => Organization::factory()->create()->id]);
        $lesson = Lesson::factory()->for(Module::factory()->for($course))->create(['type' => 'quiz']);
        $quiz = Quiz::factory()->for($lesson)->create();
        $question = QuizQuestion::factory()->for($quiz)->create();
        $user = User::factory()->create();
        $attempt = QuizAttempt::factory()->for($quiz)->for($user)->create();
        $answer = QuizAnswer::factory()->for($attempt, 'attempt')->for($question, 'question')->create();

        $this->assertTrue($question->answers->contains($answer));
    }
}
