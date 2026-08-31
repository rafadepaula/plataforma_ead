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
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E do player unificado de aula. Um método por formato (vídeo, PDF,
 * texto/imagem, prova) e por caminho degradado: assim a falha de um formato
 * não esconde os demais nem impede as asserções de banco do próprio formato.
 */
class LessonPlayerDuskTest extends DuskTestCase
{
    private Course $course;

    private Module $module;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $this->course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $this->module = Module::factory()->create(['course_id' => $this->course->id]);

        CourseCompletionRule::query()->create([
            'course_id' => $this->course->id,
            'rule_type' => 'all_lessons',
            'required_percentage' => 100,
        ]);

        $this->student = User::factory()->create();
        $this->student->assignRole(RolesEnum::ALUNO->value);
        $this->course->students()->attach($this->student->id, ['enrolled_at' => now(), 'status' => 'active']);
    }

    public function test_video_lesson_shows_the_player_shell_and_auto_completes_at_the_threshold(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula em Vídeo',
            'type' => 'content',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'order_index' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-player-'.$lesson->id)
                ->assertSee('Meus cursos')
                ->assertSee($this->course->title)
                ->assertSee($lesson->title)
                ->assertSeeIgnoringCase($this->course->title.' / '.$this->module->title)
                ->assertSee('Continue seus estudos e marque a lição como concluída ao terminar.')
                ->assertSee('Voltar à sala de aula')
                ->assertAttribute('@video-player-'.$lesson->id, 'data-video-id', 'dQw4w9WgXcQ')
                ->assertMissing('@video-unavailable-'.$lesson->id)
                ->assertMissing('@mark-complete-button')
                ->assertSee('O progresso é salvo automaticamente ao assistir o vídeo.');

            // Atinge o limiar de 90% pela costura pública do player.
            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 90, 100);");

            $browser->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                ->waitForText('Lição concluída automaticamente!');

            // A conclusão automática avisa UMA única vez, mesmo que o polling
            // continue reportando progresso depois do limiar.
            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 95, 100);");
            $browser->pause(700);

            $toastCount = $browser->script(
                'return document.querySelectorAll("#notification-container .toast").length;'
            )[0];

            $this->assertSame(1, (int) $toastCount, 'A conclusão automática deve notificar apenas uma vez.');
        });

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'video_threshold',
        ]);
    }

    public function test_video_lesson_with_an_unrecognizable_url_falls_back_to_manual_completion(): void
    {
        $lesson = $this->lesson([
            'title' => 'Vídeo Não Reconhecido',
            'type' => 'content',
            'order_index' => 6,
        ]);
        DB::table('lessons')->where('id', $lesson->id)->update(['youtube_url' => 'https://vimeo.com/999999']);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-unavailable-'.$lesson->id)
                ->assertSeeIn('@video-unavailable-'.$lesson->id, 'Vídeo indisponível')
                ->assertMissing('@video-player-'.$lesson->id.' iframe')
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                ->waitForText('Lição concluída com sucesso.');
        });

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    public function test_pdf_lesson_shows_the_viewer_the_download_link_and_completes_manually(): void
    {
        $path = 'lessons/dusk-sample-'.uniqid().'.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4');

        $lesson = $this->lesson([
            'title' => 'Aula em PDF',
            'type' => 'content',
            'pdf_path' => $path,
            'order_index' => 2,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($lesson): void {
                $browser->loginAs($this->student)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@pdf-viewer-'.$lesson->id)
                    ->assertPresent('@pdf-download-'.$lesson->id)
                    ->waitFor('@mark-complete-button')
                    ->click('@mark-complete-button')
                    ->waitFor('@lesson-completed-badge')
                    ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                    ->waitForText('Lição concluída com sucesso.')
                    ->assertMissing('@mark-complete-button');
            });
        } finally {
            Storage::disk('public')->delete($path);
        }

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    public function test_pdf_lesson_whose_file_is_missing_shows_a_notice_and_still_completes(): void
    {
        $lesson = $this->lesson([
            'title' => 'PDF Ausente',
            'type' => 'content',
            'pdf_path' => 'lessons/dusk-inexistente-'.uniqid().'.pdf',
            'order_index' => 7,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@pdf-unavailable-'.$lesson->id)
                ->assertSeeIn('@pdf-unavailable-'.$lesson->id, 'Documento indisponível')
                ->assertMissing('@pdf-viewer-'.$lesson->id)
                ->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitFor('@lesson-completed-badge');
        });

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    public function test_text_and_image_lesson_renders_the_content_and_completes_manually(): void
    {
        $path = 'lessons/dusk-sample-'.uniqid().'.png';
        Storage::disk('public')->put($path, 'fake-png');

        $lesson = $this->lesson([
            'title' => 'Aula em Texto e Imagem',
            'type' => 'content',
            'image_path' => $path,
            'content_text' => "Texto introdutório da lição.\nSegunda linha do conteúdo.",
            'order_index' => 3,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($lesson): void {
                $browser->loginAs($this->student)
                    ->visit(route('classroom.lesson', $lesson))
                    ->waitFor('@lesson-image-'.$lesson->id)
                    ->assertPresent('@lesson-content-'.$lesson->id)
                    ->assertSeeIn('@lesson-content-'.$lesson->id, 'Texto introdutório da lição.')
                    ->waitFor('@mark-complete-button')
                    ->click('@mark-complete-button')
                    ->waitFor('@lesson-completed-badge')
                    ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                    ->waitForText('Lição concluída com sucesso.');
            });
        } finally {
            Storage::disk('public')->delete($path);
        }

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
        ]);
    }

    /**
     * O aviso de cronômetro é a única coisa que conta ao Aluno, ANTES do
     * clique, que iniciar a prova já dispara a contagem e consome a
     * tentativa mesmo se ele fechar a tela. Por isso o teste cobre os dois
     * lados: ele aparece na prova cronometrada e não aparece na prova sem
     * limite de tempo, onde a afirmação seria falsa.
     */
    public function test_ready_quiz_lesson_hands_off_to_the_quiz_screen(): void
    {
        $lesson = $this->lesson([
            'title' => 'Avaliação Final',
            'type' => 'quiz',
            'order_index' => 4,
        ]);

        $quiz = Quiz::factory()->for($lesson)->create([
            'time_limit_minutes' => 20,
            'min_score_percentage' => 70,
        ]);
        $question = QuizQuestion::factory()->for($quiz)->singleChoice()->create([
            'question_text' => 'Qual é a resposta correta?',
        ]);
        QuizOption::factory()->for($question, 'question')->correct()->create(['option_text' => 'Opção A']);
        QuizOption::factory()->for($question, 'question')->incorrect()->create(['option_text' => 'Opção B']);

        $untimedLesson = $this->lesson([
            'title' => 'Avaliação Sem Cronômetro',
            'type' => 'quiz',
            'order_index' => 6,
        ]);
        $untimedQuiz = Quiz::factory()->for($untimedLesson)->create([
            'time_limit_minutes' => null,
            'min_score_percentage' => 70,
        ]);
        $untimedQuestion = QuizQuestion::factory()->for($untimedQuiz)->singleChoice()->create([
            'question_text' => 'Qual é a resposta correta?',
        ]);
        QuizOption::factory()->for($untimedQuestion, 'question')->correct()->create(['option_text' => 'Opção A']);
        QuizOption::factory()->for($untimedQuestion, 'question')->incorrect()->create(['option_text' => 'Opção B']);

        $this->browse(function (Browser $browser) use ($lesson, $untimedLesson): void {
            // 1. Prova cronometrada: o aviso avisa antes do clique.
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@quiz-placeholder')
                ->assertSeeIn('@quiz-placeholder', 'Esta aula é uma prova')
                ->assertVisible('@quiz-timer-warning')
                ->assertSeeIn('@quiz-timer-warning', 'o cronômetro começa a correr')
                ->assertSeeIn('@quiz-timer-warning', 'mesmo que você feche a tela antes de enviar')
                ->assertPresent('@start-quiz');

            // 2. Prova sem limite de tempo: o aviso seria falso, então some.
            $browser->visit(route('classroom.lesson', $untimedLesson))
                ->waitFor('@quiz-placeholder')
                ->assertPresent('@start-quiz')
                ->assertMissing('@quiz-timer-warning');

            // 3. O botão continua entregando o Aluno à tela da prova.
            $browser->visit(route('classroom.lesson', $lesson))
                ->waitFor('@start-quiz')
                ->click('@start-quiz')
                ->waitForLocation('/lessons/'.$lesson->id.'/quiz');
        });
    }

    public function test_quiz_lesson_without_questions_shows_the_in_preparation_placeholder(): void
    {
        $lesson = $this->lesson([
            'title' => 'Prova em Preparação',
            'type' => 'quiz',
            'order_index' => 5,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@quiz-placeholder')
                ->assertSeeIn('@quiz-placeholder', 'Prova em preparação')
                ->assertMissing('@start-quiz')
                ->assertMissing('@mark-complete-button');
        });
    }

    /**
     * Cria uma lição publicada do módulo do teste, com todas as colunas de
     * conteúdo zeradas por padrão para o despacho de formato ser exclusivo.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function lesson(array $attributes): Lesson
    {
        return Lesson::factory()->create($attributes + [
            'module_id' => $this->module->id,
            'youtube_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
        ]);
    }
}
