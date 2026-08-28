<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\CourseCompletionRule;
use App\Models\Lesson;
use App\Models\LessonProgress;
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
     * Caminho de erro do botão manual: o backend responde 422 e o player deve
     * devolver o botão utilizável, com o rótulo original, e avisar o aluno.
     */
    public function test_failed_manual_completion_restores_the_button_and_shows_an_error_toast(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula em Texto',
            'type' => 'content',
            'content_text' => 'Texto da lição.',
            'order_index' => 8,
        ]);

        $quizLesson = $this->lesson([
            'title' => 'Prova Que Recusa Conclusão Manual',
            'type' => 'quiz',
            'order_index' => 9,
        ]);

        $this->browse(function (Browser $browser) use ($lesson, $quizLesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@mark-complete-button');

            // Aponta o botão para um endpoint que responde 422 (conclusão
            // manual de prova é sempre recusada).
            $browser->script(
                'document.querySelector(\'[dusk="mark-complete-button"]\')'
                .".setAttribute('data-mark-complete-url', '/lessons/{$quizLesson->id}/complete');"
            );

            $browser->click('@mark-complete-button')
                ->waitForText('Falha ao concluir a lição')
                ->assertVisible('@mark-complete-button')
                ->assertSeeIn('@mark-complete-button', 'Marcar como concluída')
                ->assertMissing('@lesson-completed-badge');

            $disabled = $browser->script(
                'return document.querySelector(\'[dusk="mark-complete-button"]\').disabled;'
            )[0];

            $this->assertFalse((bool) $disabled, 'O botão deve voltar a ser clicável depois do erro.');
        });

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    /**
     * Caminho de erro do polling de vídeo: uma lição sem vídeo recusa o
     * endpoint de progresso e o aluno é avisado em vez de ficar no escuro.
     */
    public function test_failed_progress_report_shows_an_error_toast(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula Sem Vídeo',
            'type' => 'content',
            'content_text' => 'Texto da lição.',
            'order_index' => 10,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@mark-complete-button');

            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 90, 100).catch(() => {});");

            $browser->waitForText('Falha ao registrar progresso')
                ->assertMissing('@lesson-completed-badge');

            // Uma falha persistente bate a cada 5s: o aviso não pode virar uma
            // fila infinita de toasts idênticos.
            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 92, 100).catch(() => {});");
            $browser->script("window.LessonPlayer.reportProgress({$lesson->id}, 94, 100).catch(() => {});");
            $browser->pause(700);

            $toastCount = $browser->script(
                'return document.querySelectorAll("#notification-container .toast").length;'
            )[0];

            $this->assertSame(1, (int) $toastCount, 'A falha de progresso deve avisar apenas uma vez.');
        });

        $this->assertDatabaseMissing('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

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

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@quiz-placeholder')
                ->assertSeeIn('@quiz-placeholder', 'Esta aula é uma prova')
                ->assertPresent('@start-quiz')
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
     * Reconclusão de uma lição já concluída: a escrita continua idempotente —
     * mesma linha, mesmo `completed_at` — e a tela permanece no estado
     * concluído. O botão nasce oculto (`.d-none`) justamente para evitar o
     * segundo clique, então o teste o revela para exercitar o caminho.
     */
    public function test_re_clicking_completion_on_an_already_completed_lesson_keeps_it_completed(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula Já Concluída',
            'type' => 'content',
            'content_text' => 'Texto da lição.',
            'order_index' => 11,
        ]);

        $completedAt = now()->subDay()->startOfSecond();

        LessonProgress::query()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
            'is_completed' => true,
            'completion_source' => 'manual_click',
            'completed_at' => $completedAt,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@lesson-completed-badge')
                ->assertSeeIn('@lesson-completed-badge', 'Concluída')
                ->assertMissing('@mark-complete-button');

            // O botão existe no DOM, apenas oculto: revela-o para reproduzir o
            // segundo clique que a UI normalmente impede.
            $browser->script(
                'document.querySelector(\'[dusk="mark-complete-button"]\').classList.remove("d-none");'
            );

            $browser->waitFor('@mark-complete-button')
                ->click('@mark-complete-button')
                ->waitForText('Lição concluída com sucesso.')
                ->assertVisible('@lesson-completed-badge')
                ->assertMissing('@mark-complete-button');
        });

        $rows = LessonProgress::query()
            ->where('user_id', $this->student->id)
            ->where('lesson_id', $lesson->id)
            ->get();

        $this->assertCount(1, $rows, 'A reconclusão não pode duplicar o progresso.');
        $this->assertTrue((bool) $rows->first()->is_completed);
        $this->assertSame(
            $completedAt->toDateTimeString(),
            $rows->first()->completed_at->toDateTimeString(),
            'A data de conclusão original deve ser preservada.'
        );
    }

    public function test_back_to_classroom_button_returns_to_the_course(): void
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
                ->waitFor('@back-to-classroom')
                ->click('@back-to-classroom')
                ->waitForLocation('/courses/'.$this->course->id.'/classroom');
        });
    }

    /**
     * Modo Visualização na tela de aula: Admin e Gestor da mesma Organização
     * abrem a lição sem matrícula, mas sem botão de conclusão e sem o polling
     * de vídeo — e o endpoint de escrita recusa a gravação com 403.
     */
    public function test_staff_previewing_a_lesson_cannot_write_progress(): void
    {
        $videoLesson = $this->lesson([
            'title' => 'Aula em Vídeo para Visualização',
            'type' => 'content',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'order_index' => 12,
        ]);

        $textLesson = $this->lesson([
            'title' => 'Aula em Texto para Visualização',
            'type' => 'content',
            'content_text' => 'Texto da lição.',
            'order_index' => 13,
        ]);

        $admin = User::factory()->create(['org_id' => null]);
        $admin->assignRole(RolesEnum::ADMIN->value);

        $gestor = User::factory()->create(['org_id' => $this->course->org_id]);
        $gestor->assignRole(RolesEnum::GESTOR->value);

        $this->browse(function (Browser $browser) use ($admin, $gestor, $videoLesson, $textLesson): void {
            foreach ([$admin, $gestor] as $staff) {
                $browser->loginAs($staff)
                    ->visit(route('classroom.lesson', $videoLesson))
                    ->waitFor('@video-player-'.$videoLesson->id)
                    ->assertMissing('@mark-complete-button')
                    ->assertMissing('@lesson-completed-badge')
                    ->assertDontSee('O progresso é salvo automaticamente ao assistir o vídeo.');

                $pollingWired = $browser->script(
                    'return document.querySelectorAll("[data-youtube-player]").length;'
                )[0];

                $this->assertSame(0, (int) $pollingWired, 'A visualização não pode ligar o polling de progresso.');

                $browser->visit(route('classroom.lesson', $textLesson))
                    ->waitForText($textLesson->title)
                    ->assertMissing('@mark-complete-button');

                // A escrita direta no endpoint continua recusada: o botão
                // some da tela, mas o 403 é o que realmente protege o dado.
                $browser->script(
                    'window.progressWriteStatus = null;'
                    .'fetch("/lessons/'.$textLesson->id.'/complete", {'
                    .'method: "POST",'
                    .'headers: {'
                    .'"X-CSRF-TOKEN": document.querySelector("meta[name=\'csrf-token\']").content,'
                    .'"X-Requested-With": "XMLHttpRequest"'
                    .'}'
                    .'}).then(function (response) { window.progressWriteStatus = response.status; });'
                );

                $browser->waitUntil('window.progressWriteStatus !== null');

                $writeStatus = $browser->script('return window.progressWriteStatus;')[0];

                $this->assertSame(403, (int) $writeStatus, 'A visualização nunca pode gravar progresso.');
            }
        });

        $this->assertDatabaseMissing('lesson_progress', ['lesson_id' => $textLesson->id]);
        $this->assertDatabaseMissing('lesson_progress', ['lesson_id' => $videoLesson->id]);
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
