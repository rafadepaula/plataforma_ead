<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * E2E do player de aula: só importa se o aluno consegue ASSISTIR ao vídeo
 * de cada provedor ao abrir a página (fachada → boot → reprodução real →
 * pausa pelos nossos controles). Detalhes de UI, estados internos e
 * seletores vivem nos testes de componente/feature — aqui é a feature
 * ponta a ponta, com stream de verdade (requer rede no Selenium).
 *
 * Os vídeos usados precisam continuar com embed liberado: se algum deles
 * for bloqueado pelo detentor, o teste quebra sem que o produto tenha
 * mudado nada (foi o que aconteceu com o dQw4w9WgXcQ).
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

        $this->student = User::factory()->create();
        $this->student->assignRole(RolesEnum::ALUNO->value);
        $this->course->students()->attach($this->student->id, ['enrolled_at' => now(), 'status' => 'active']);
    }

    public function test_student_can_watch_youtube_video_lesson(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula em Vídeo YouTube',
            'type' => 'content',
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=aqz-KE-bpKQ',
            'order_index' => 1,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $this->watchUntilPlaying($browser, $lesson);
            $this->pauseThroughControls($browser, $lesson);
        });
    }

    public function test_student_can_watch_vimeo_video_lesson(): void
    {
        $lesson = $this->lesson([
            'title' => 'Aula em Vídeo Vimeo',
            'type' => 'content',
            'video_provider' => 'vimeo',
            'video_url' => 'https://vimeo.com/22439234',
            'order_index' => 2,
        ]);

        $this->browse(function (Browser $browser) use ($lesson): void {
            $this->watchUntilPlaying($browser, $lesson);

            // O iframe precisa preencher o stage (regressão do vídeo
            // minúsculo no canto do player).
            $rects = $browser->script(<<<'JS'
                return (() => {
                    const stage = document.querySelector('[data-video-player] [data-player-stage]');
                    const iframe = document.querySelector('[data-video-player] iframe');
                    if (!stage || !iframe) return null;
                    const s = stage.getBoundingClientRect();
                    const f = iframe.getBoundingClientRect();
                    return {stageW: s.width, stageH: s.height, frameW: f.width, frameH: f.height};
                })();
            JS)[0];

            $this->assertNotNull($rects, 'O player deveria ter stage e iframe após o boot.');
            $this->assertEqualsWithDelta((float) $rects['stageW'], (float) $rects['frameW'], 2.0);
            $this->assertEqualsWithDelta((float) $rects['stageH'], (float) $rects['frameH'], 2.0);

            $this->pauseThroughControls($browser, $lesson);
        });
    }

    /**
     * Abre a aula, clica na fachada e insiste como um usuário real até o
     * vídeo entrar em reprodução (o boot baixa o SDK e o autoplay pode
     * perder a corrida).
     */
    private function watchUntilPlaying(Browser $browser, Lesson $lesson): void
    {
        $browser->loginAs($this->student)
            ->visit(route('classroom.lesson', $lesson))
            ->waitFor('@video-player-'.$lesson->id)
            ->assertVisible('@video-facade-'.$lesson->id)
            ->click('@video-facade-'.$lesson->id);

        $browser->waitUsing(60, 1000, function () use ($browser, $lesson): bool {
            $state = $browser->script("return window.LessonPlayer.playerState({$lesson->id});")[0];

            if (in_array($state, ['playing', 'buffering'], true)) {
                return true;
            }

            $browser->click('@video-player-'.$lesson->id);

            return false;
        }, 'O vídeo deveria entrar em reprodução ao abrir a aula.');
    }

    /**
     * Pausa pelo botão da barra de controles (que auto-esconde durante a
     * reprodução — por isso o hover antes do clique).
     */
    private function pauseThroughControls(Browser $browser, Lesson $lesson): void
    {
        $browser->waitUsing(30, 500, function () use ($browser, $lesson): bool {
            return $browser->script("return window.LessonPlayer.playerState({$lesson->id});")[0] === 'playing';
        }, 'O vídeo deveria estar em reprodução antes de pausar.');

        $browser->mouseover('@video-player-'.$lesson->id)
            ->click('@video-play-'.$lesson->id);

        $browser->waitUsing(10, 250, function () use ($browser, $lesson): bool {
            return $browser->script("return window.LessonPlayer.playerState({$lesson->id});")[0] === 'paused';
        }, 'O vídeo deveria pausar pelo botão da barra de controles.');
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
