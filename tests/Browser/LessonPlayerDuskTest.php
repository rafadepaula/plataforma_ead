<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class LessonPlayerDuskTest extends DuskTestCase
{
    public function test_unified_lesson_player_lifecycle_across_all_formats(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        CourseCompletionRule::query()->create([
            'course_id' => $course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        // 1. Video Lesson
        $videoLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Aula em Vídeo',
            'type' => 'content',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
            'order_index' => 1,
        ]);

        // 2. PDF Lesson
        $pdfLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Aula em PDF',
            'type' => 'content',
            'youtube_url' => null,
            'pdf_path' => 'lessons/sample.pdf',
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
            'order_index' => 2,
        ]);

        // 3. Text & Image Lesson
        $textLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Aula em Texto e Imagem',
            'type' => 'content',
            'youtube_url' => null,
            'pdf_path' => null,
            'image_path' => 'lessons/sample.png',
            'content_text' => "Texto introdutório da lição.\nSegunda linha do conteúdo.",
            'is_published' => true,
            'order_index' => 3,
        ]);

        // 4. Quiz Lesson (ready)
        $quizLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Avaliação Final',
            'type' => 'quiz',
            'youtube_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
            'order_index' => 4,
        ]);
        $quiz = Quiz::factory()->for($quizLesson)->create([
            'time_limit_minutes' => 20,
            'min_score_percentage' => 70,
        ]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a resposta correta?',
        ]);
        QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Opção A']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'Opção B']);

        // 5. Quiz Lesson (in preparation - no questions)
        $prepQuizLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Prova em Preparação',
            'type' => 'quiz',
            'youtube_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
            'order_index' => 5,
        ]);

        // 6. Degraded Video Lesson (invalid URL)
        $brokenVideoLesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Vídeo Não Reconhecido',
            'type' => 'content',
            'is_published' => true,
            'order_index' => 6,
        ]);
        DB::table('lessons')->where('id', $brokenVideoLesson->id)->update(['youtube_url' => 'https://vimeo.com/999999']);
        $brokenVideoLesson = $brokenVideoLesson->fresh();

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use (
            $student,
            $course,
            $videoLesson,
            $pdfLesson,
            $textLesson,
            $quizLesson,
            $prepQuizLesson,
            $brokenVideoLesson
        ): void {
            // 1. Video Lesson format: shell breadcrumb, player embed, automatic progress & 90% auto-completion
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $videoLesson))
                ->waitFor('@video-player-'.$videoLesson->id)
                ->assertSee('Meus cursos')
                ->assertSee($course->title)
                ->assertSee($videoLesson->title)
                ->assertSeeIgnoringCase($course->title.' / '.$videoLesson->module->title)
                ->assertSee('Continue seus estudos e marque a lição como concluída ao terminar.')
                ->assertSee('Voltar à sala de aula')
                ->assertAttribute('@video-player-'.$videoLesson->id, 'data-video-id', 'dQw4w9WgXcQ')
                ->assertMissing('@video-unavailable-'.$videoLesson->id)
                ->assertMissing('@mark-complete-button')
                ->assertSee('O progresso é salvo automaticamente ao assistir o vídeo.');

            // Trigger progress to 90% via LessonPlayer seam
            $browser->script("window.LessonPlayer.reportProgress({$videoLesson->id}, 90, 100);");
            $browser->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída');

            // 2. PDF Lesson format: PDF iframe, download link, manual completion
            $browser->visit(route('classroom.lesson', $pdfLesson))
                ->waitFor('@pdf-viewer-'.$pdfLesson->id)
                ->assertPresent('@pdf-download-'.$pdfLesson->id)
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída');

            // 3. Text/Image Lesson format: image, pre-wrap text, manual completion
            $browser->visit(route('classroom.lesson', $textLesson))
                ->waitFor('@lesson-image-'.$textLesson->id)
                ->assertPresent('@lesson-content-'.$textLesson->id)
                ->assertSeeIn('@lesson-content-'.$textLesson->id, 'Texto introdutório da lição.')
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída');

            // 4. Quiz Lesson (ready): quiz handoff CTA and "Iniciar prova" button
            $browser->visit(route('classroom.lesson', $quizLesson))
                ->waitFor('@quiz-placeholder')
                ->assertSeeIn('@quiz-placeholder', 'Esta aula é uma prova')
                ->assertPresent('@start-quiz')
                ->click('@start-quiz')
                ->waitForLocation('/lessons/'.$quizLesson->id.'/quiz');

            // 5. Quiz Lesson (in preparation): informational message without start CTA
            $browser->visit(route('classroom.lesson', $prepQuizLesson))
                ->waitFor('@quiz-placeholder')
                ->assertSeeIn('@quiz-placeholder', 'Prova em preparação')
                ->assertMissing('@start-quiz')
                ->assertMissing('@mark-complete-button');

            // 6. Degraded Video Lesson: unavailable alert
            $browser->visit(route('classroom.lesson', $brokenVideoLesson))
                ->waitFor('@video-unavailable-'.$brokenVideoLesson->id)
                ->assertSeeIn('@video-unavailable-'.$brokenVideoLesson->id, 'Vídeo indisponível')
                ->assertMissing('@video-player-'.$brokenVideoLesson->id.' iframe');

            // 7. Back to classroom button navigation
            $browser->visit(route('classroom.lesson', $videoLesson))
                ->waitFor('@back-to-classroom')
                ->click('@back-to-classroom')
                ->waitForLocation('/courses/'.$course->id.'/classroom');
        });

        // Verify database completions
        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $videoLesson->id,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $pdfLesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $student->id,
            'lesson_id' => $textLesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }
}
