<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 *  E2E coverage of the student's video player against the stored
 * `lessons.youtube_url` value. The `<iframe>` may only ever carry an
 * embeddable `youtube.com/embed/{id}` src (YouTube answers anything else with
 * `X-Frame-Options: SAMEORIGIN` — the "refused to connect" sad face), and an
 * unrecognizable stored value must surface an explicit notice rather than a
 * broken frame.
 *
 * Agrupado por cadeia de ciclo de vida (ver `testing-conventions`): o mesmo
 * Aluno matriculado percorre as duas lições do mesmo Curso — a com URL
 * legada reconhecível e a com URL irreconhecível — numa sessão só.
 */
class StudentVideoLessonEmbedTest extends DuskTestCase
{
    /**
     * Cria uma lição de vídeo publicada carregando o valor bruto informado
     * (contornando todo caminho de escrita sanitizador).
     */
    private function videoLessonWithStoredUrl(Module $module, string $storedYoutubeUrl): Lesson
    {
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'type' => 'content',
            'is_published' => true,
        ]);

        DB::table('lessons')->where('id', $lesson->id)->update(['youtube_url' => $storedYoutubeUrl]);

        return $lesson->fresh();
    }

    public function test_student_video_embed_states_lifecycle(): void
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id]);

        $legacyLesson = $this->videoLessonWithStoredUrl($module, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $brokenLesson = $this->videoLessonWithStoredUrl($module, 'https://vimeo.com/123456789');

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        $this->browse(function (Browser $browser) use ($student, $legacyLesson, $brokenLesson): void {
            // 1. URL legada `watch?v=`: o container do player carrega o id real
            //    de 11 caracteres.
            //
            //    O `<iframe>` estático deliberadamente NÃO é asserido aqui:
            //    `YT.Player()` troca o container inteiro pelo próprio frame
            //    assim que a IFrame API carrega, então qualquer asserção sobre
            //    ele disputa com a rede. O `src` é coberto de forma
            //    determinística por `LessonYoutubeEmbedRenderingTest`; o que
            //    importa no navegador é o id do vídeo (`"watch"` mataria
            //    silenciosamente o reporte de progresso).
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $legacyLesson))
                ->waitFor('@video-player-'.$legacyLesson->id)
                ->assertAttribute('@video-player-'.$legacyLesson->id, 'data-video-id', 'dQw4w9WgXcQ')
                ->assertMissing('@video-unavailable-'.$legacyLesson->id)
                ->assertSee('O progresso é salvo automaticamente ao assistir o vídeo.');

            // 2. URL irreconhecível: aviso explícito, nunca um frame quebrado.
            $browser->visit(route('classroom.lesson', $brokenLesson))
                ->waitFor('@video-unavailable-'.$brokenLesson->id)
                ->assertSeeIn('@video-unavailable-'.$brokenLesson->id, 'Vídeo indisponível')
                ->assertMissing('@video-player-'.$brokenLesson->id.' iframe');
        });

        $this->assertDatabaseHas('lessons', [
            'id' => $brokenLesson->id,
            'youtube_url' => 'https://vimeo.com/123456789',
        ]);
    }
}
