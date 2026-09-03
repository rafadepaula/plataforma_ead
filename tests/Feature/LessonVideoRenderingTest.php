<?php

namespace Tests\Feature;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\CourseSeeder;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Render contract of the classroom video partial across BOTH supported
 * providers: the custom player mounts from data attributes (never a raw
 * server-side `<iframe>` — the embed only exists after the student clicks
 * the facade), the stored URL format is never trusted at render time, and
 * an unresolvable URL degrades to the neutral unavailable notice with
 * manual completion back open.
 */
class LessonVideoRenderingTest extends TestCase
{
    use RefreshDatabase;

    /**
     *  Scenario A: CourseSeeder must persist `video_url` in sanitized
     * (privacy-enhanced) embed form with the `video_provider` stamp, never
     * in raw `watch?v=` form.
     */
    public function test_course_seeder_persists_sanitized_youtube_embed_urls(): void
    {
        // Seed the base organizations that CourseSeeder depends on
        $this->seed(OrganizationSeeder::class);
        $this->seed(UserSeeder::class);
        $this->seed(CourseSeeder::class);

        $embedPattern = '#^https://www\.youtube-nocookie\.com/embed/[A-Za-z0-9_-]{11}$#';

        $videoLessons = Lesson::whereNotNull('video_url')->get();

        $this->assertGreaterThan(0, $videoLessons->count(), 'Expected at least one video lesson from CourseSeeder.');

        foreach ($videoLessons as $lesson) {
            $this->assertSame('youtube', $lesson->getRawOriginal('video_provider'), "Lesson #{$lesson->id} must be stamped as a YouTube lesson.");
            $this->assertMatchesRegularExpression(
                $embedPattern,
                $lesson->video_url,
                "Lesson #{$lesson->id} (\"{$lesson->title}\") video_url is not in embed form: {$lesson->video_url}"
            );
        }

        // Assert the specific known lesson has the correct embed URL
        $videoLesson = Lesson::where('title', 'Videoaula — Circuito Residencial Passo a Passo')->first();
        $this->assertNotNull($videoLesson);
        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ',
            $videoLesson->video_url
        );
    }

    /**
     *  Scenario B: the student classroom view must render the CUSTOM
     * player wiring for a YouTube lesson — provider, resolved video id and
     * the privacy-enhanced embed URL travel as data attributes; the native
     * provider UI never ships in the server HTML.
     */
    public function test_student_lesson_view_renders_the_custom_player_for_a_youtube_lesson(): void
    {
        $lesson = $this->enrolledAlunoWatching($this->createPublishedLesson([
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ]));

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-player', false);
        $response->assertSee('data-provider="youtube"', false);
        $response->assertSee('data-video-id="dQw4w9WgXcQ"', false);
        $response->assertSee('data-video-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false);
        $response->assertSee('dusk="video-facade-'.$lesson->id.'"', false);
        $response->assertSee('data-player-controls', false);
        $response->assertSee('data-progress-url', false);
        // The embed must NEVER render server-side: the player only exists
        // after the student clicks the facade.
        $response->assertDontSee('<iframe', false);
    }

    /**
     *  the consumer must not trust the stored format: a legacy
     * `watch?v=` row (written before the sanitizer existed, or by a direct
     * `UPDATE`) must still resolve the real 11-char video id and the
     * embeddable form, never `data-video-id="watch"`.
     */
    public function test_student_lesson_view_normalizes_legacy_watch_urls_at_render_time(): void
    {
        $lesson = $this->publishedVideoLessonFor('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube');

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-id="dQw4w9WgXcQ"', false);
        $response->assertSee('data-video-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', false);
        $response->assertDontSee('data-video-id="watch"', false);
    }

    /**
     *  when no video id can be resolved from the stored value, the
     * view must degrade explicitly (visible notice, no player wiring,
     * manual completion back open) instead of shipping a player that has
     * nothing to play.
     */
    public function test_student_lesson_view_degrades_when_video_id_cannot_be_resolved(): void
    {
        // Provider left unknown: the URL parses to nothing, the same drift
        // shape a typo or a foreign link produces in the wild.
        $lesson = $this->publishedVideoLessonFor('https://vimeo.com/12345', null);

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('dusk="video-unavailable-'.$lesson->id.'"', false);
        $response->assertDontSee('<iframe', false);
        $response->assertDontSee('data-video-player', false);
        $response->assertSee('dusk="video-player-'.$lesson->id.'"', false);
        $response->assertSee('dusk="mark-complete-button"', false);
    }

    public function test_student_lesson_view_renders_the_custom_player_for_a_vimeo_lesson(): void
    {
        $lesson = $this->enrolledAlunoWatching($this->createPublishedLesson([
            'video_provider' => 'vimeo',
            'video_url' => 'https://player.vimeo.com/video/76979871',
        ]));

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-player', false);
        $response->assertSee('data-provider="vimeo"', false);
        $response->assertSee('data-video-id="76979871"', false);
        $response->assertSee('data-video-embed="https://player.vimeo.com/video/76979871"', false);
        $response->assertSee('dusk="video-facade-'.$lesson->id.'"', false);
        $response->assertDontSee('<iframe', false);
    }

    public function test_a_vimeo_unlisted_lesson_keeps_the_hash_on_the_embed_attribute(): void
    {
        $lesson = $this->enrolledAlunoWatching($this->createPublishedLesson([
            'video_provider' => 'vimeo',
            'video_url' => 'https://vimeo.com/76979871/abcdef12345',
        ]));

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-embed="https://player.vimeo.com/video/76979871?h=abcdef12345"', false);
    }

    /**
     *  Regression: when video_url is already sanitized (embed form),
     * the view must still extract the correct video id — extraction is
     * generic, not coupled to one stored shape.
     */
    public function test_student_lesson_view_renders_correct_video_id_data_attribute(): void
    {
        // Use a different known video ID to prove extraction works generically
        $lesson = $this->enrolledAlunoWatching($this->createPublishedLesson([
            'video_provider' => 'youtube',
            'video_url' => 'https://www.youtube-nocookie.com/embed/9bZkp7q19f0',
        ]));

        $response = $this->get(route('classroom.lesson', $lesson));

        $response->assertOk();
        $response->assertSee('data-video-id="9bZkp7q19f0"', false);
    }

    /**
     * Creates a published content lesson under a fresh org/course/module
     * chain with the given lesson attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createPublishedLesson(array $attributes = []): Lesson
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();

        return Lesson::factory()->for($module)->create(array_merge([
            'type' => 'content',
            'is_published' => true,
        ], $attributes));
    }

    /**
     * Creates a published video lesson with the given raw stored value
     * (bypassing every sanitizing write path) and authenticates an enrolled
     * ALUNO for the request.
     */
    private function publishedVideoLessonFor(string $storedVideoUrl, ?string $provider): Lesson
    {
        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $module = Module::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($module)->create([
            'type' => 'content',
            'is_published' => true,
        ]);

        DB::table('lessons')->where('id', $lesson->id)->update([
            'video_url' => $storedVideoUrl,
            'video_provider' => $provider,
        ]);

        $this->enrolledAlunoWatching($lesson);

        return $lesson->fresh();
    }

    /**
     * Creates + authenticates an ALUNO with an active enrollment on the
     * lesson's course (the full polling wiring only ships for enrollment
     * holders) and returns the lesson.
     */
    private function enrolledAlunoWatching(Lesson $lesson): Lesson
    {
        $aluno = User::factory()->create(['org_id' => null]);
        $aluno->assignRole(RolesEnum::ALUNO->value);
        $aluno->courses()->attach($lesson->module->course_id, [
            'status' => 'active',
            'enrolled_at' => now(),
        ]);

        $this->actingAs($aluno);

        return $lesson;
    }
}
