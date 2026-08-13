<?php

namespace Tests\Browser;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * BUG-002 — E2E coverage of the student's video player against the stored
 * `lessons.youtube_url` value. The `<iframe>` may only ever carry an
 * embeddable `youtube.com/embed/{id}` src (YouTube answers anything else with
 * `X-Frame-Options: SAMEORIGIN` — the "refused to connect" sad face), and an
 * unrecognizable stored value must surface an explicit notice rather than a
 * broken frame. The iframe's `src` attribute is asserted without ever loading
 * YouTube itself, which is network-bound and out of scope here.
 */
class StudentVideoLessonEmbedTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Creates a published video lesson holding the given raw stored value
     * (bypassing every sanitizing write path) plus an enrolled ALUNO.
     *
     * @return array{0: Lesson, 1: User}
     */
    private function videoLessonWithStoredUrl(string $storedYoutubeUrl): array
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id, 'is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'type' => 'content',
            'is_published' => true,
        ]);

        DB::table('lessons')->where('id', $lesson->id)->update(['youtube_url' => $storedYoutubeUrl]);

        $student = User::factory()->create();
        $student->assignRole(RolesEnum::ALUNO->value);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'status' => 'active']);

        return [$lesson->fresh(), $student];
    }

    public function test_a_legacy_watch_url_still_renders_an_embeddable_iframe_for_the_student(): void
    {
        [$lesson, $student] = $this->videoLessonWithStoredUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

        // The static `<iframe>` is deliberately NOT asserted here: `YT.Player()`
        // swaps that whole container out for its own frame as soon as the
        // IFrame API finishes loading, so any assertion on it races the
        // network. Its `src` is covered deterministically by
        // `LessonYoutubeEmbedRenderingTest`; what matters in the browser is
        // that the player container carries the real 11-char video id
        // (`"watch"` would silently kill progress reporting).
        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-player-'.$lesson->id)
                ->assertAttribute('@video-player-'.$lesson->id, 'data-video-id', 'dQw4w9WgXcQ')
                ->assertMissing('@video-unavailable-'.$lesson->id)
                ->assertSee('O progresso é salvo automaticamente ao assistir o vídeo.');
        });
    }

    public function test_an_unrecognizable_url_degrades_to_a_visible_notice_instead_of_a_broken_iframe(): void
    {
        [$lesson, $student] = $this->videoLessonWithStoredUrl('https://vimeo.com/123456789');

        $this->browse(function (Browser $browser) use ($student, $lesson): void {
            $browser->loginAs($student)
                ->visit(route('classroom.lesson', $lesson))
                ->waitFor('@video-unavailable-'.$lesson->id)
                ->assertSeeIn('@video-unavailable-'.$lesson->id, 'Vídeo indisponível')
                ->assertMissing('@video-player-'.$lesson->id.' iframe');
        });
    }
}
