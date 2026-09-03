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
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
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
                ->assertAttribute('@video-player-'.$lesson->id, 'data-provider', 'youtube')
                // Player customizado: a fachada é visível e os controles
                // overlay (inertes até o boot) existem no DOM.
                ->assertVisible('@video-facade-'.$lesson->id)
                ->assertPresent('@video-play-'.$lesson->id)
                ->assertPresent('@video-seek-'.$lesson->id)
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

    /**
     *  Os controles do player são dirigidos por CLIQUE REAL do Selenium —
     * não por `script()` — porque é exatamente o hit-testing de ponteiro que
     * quebra quando o overlay de controles herda `top:0/height:100%` de
     * `.ds-ratio > *` (barra centralizada sobre o vídeo, seek atravessando a
     * área de clique). Fluxo: clique na fachada boota o adapter (que já
     * inicia a reprodução), clique no MEIO do vídeo pausa, novo clique
     * retoma e o botão da barra pausa de novo. Requer rede no Selenium para
     * carregar a IFrame API do YouTube.
     */
    public function test_video_player_real_clicks_toggle_playback_through_our_controls(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula em Vídeo com Clique Real',
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'order_index' => 8,
        ]);

        $playerState = "return window.LessonPlayer.playerState({$lesson->id});";
        // Um único `return <expressão>`: o executeScript do php-webdriver não
        // digere múltiplos statements (um `const` antes do return volta null).
        $isPlaying = "return ['playing', 'buffering'].includes(window.LessonPlayer.playerState({$lesson->id}));";

        $this->browse(function (Browser $browser) use ($lesson, $playerState, $isPlaying): void {
            $browser->loginAs($this->student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-player-'.$lesson->id)
                ->click('@video-facade-'.$lesson->id)
                ->waitFor('@video-play-'.$lesson->id);

            // O embed do YouTube nasce SEM legendas.
            $iframeSrc = (string) $browser->script(
                "return document.querySelector('[data-video-player] iframe').src;"
            )[0];
            self::assertStringContainsString('cc_load_policy=0', $iframeSrc);

            // O boot tenta autoplay com a ativação do clique na fachada, mas
            // a política de autoplay pode descartá-la (SDK carregou tarde) —
            // nesse estado o vídeo fica parado e o CLIQUE no meio do vídeo
            // (área pointer-events:none do iframe, handler do container)
            // inicia. Espera o player assentar pós-boot: um playVideo atirado
            // durante o init do embed é engolido pelo próprio YouTube. As
            // transições assertam o ESTADO do adapter — pausa é
            // determinística; reprodução aceita 'buffering' porque a rede do
            // Selenium faz o vídeo oscilar playing/buffering.
            $browser->pause(2500);

            // O YouTube engole playVideo perdido durante o init do embed ou
            // sob política de autoplay restritiva: como um usuário real,
            // clica de novo (até 3 tentativas) enquanto não houver
            // reprodução. Se já estiver tocando (autoplay venceu), não clica.
            $attempts = 0;
            while (! (bool) $browser->script($isPlaying)[0] && $attempts < 3) {
                $attempts++;
                $browser->click('@video-player-'.$lesson->id);
                $browser->pause(1500);
            }

            $browser->waitUsing(10, 250, function () use ($browser, $isPlaying): bool {
                self::assertTrue((bool) $browser->script($isPlaying)[0], 'O vídeo deveria estar em reprodução (playing ou buffering).');

                return true;
            });

            // Clique no MEIO do vídeo pausa: estado vai a 'paused'.
            $browser->click('@video-player-'.$lesson->id)
                ->waitUsing(5, 200, function () use ($browser, $playerState): bool {
                    self::assertSame('paused', (string) $browser->script($playerState)[0]);

                    return true;
                });

            // Clique de novo no vídeo retoma.
            $browser->click('@video-player-'.$lesson->id)
                ->waitUsing(10, 250, function () use ($browser, $isPlaying): bool {
                    self::assertTrue((bool) $browser->script($isPlaying)[0], 'O vídeo deveria ter retomado.');

                    return true;
                });

            // O botão da barra (no rodapé do player) pausa também.
            $browser->click('@video-play-'.$lesson->id)
                ->waitUsing(5, 200, function () use ($browser, $playerState): bool {
                    self::assertSame('paused', (string) $browser->script($playerState)[0]);

                    return true;
                });
        });
    }

    public function test_video_lesson_with_an_unrecognizable_url_falls_back_to_manual_completion(): void
    {
        $lesson = $this->lesson([
            'title' => 'Vídeo Não Reconhecido',
            'type' => 'content',
            'order_index' => 6,
        ]);
        DB::table('lessons')->where('id', $lesson->id)->update(['video_url' => 'https://vimeo.com/12345', 'video_provider' => null]);

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
            'video_provider' => null,
            'video_url' => null,
            'pdf_path' => null,
            'image_path' => null,
            'content_text' => null,
            'is_published' => true,
        ]);
    }
}
