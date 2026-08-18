<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 *  covers the `2026_08_13_000001_normalize_lesson_youtube_urls`
 * data migration against the real legacy formats that could reach the
 * `lessons.youtube_url` column, plus the edge cases where the migration must
 * do nothing at all.
 *
 * `RefreshDatabase` has already run every migration by the time these tests
 * execute, so the legacy rows are written afterwards (via the query builder,
 * bypassing the FormRequest sanitizer exactly like the legacy write paths did)
 * and the migration's `up()` is then invoked directly.
 */
class MigrateRawYoutubeUrlsToEmbedTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $org = Organization::factory()->create();
        $course = Course::factory()->create(['org_id' => $org->id]);
        $this->module = Module::factory()->for($course)->create();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_08_13_000001_normalize_lesson_youtube_urls.php');
    }

    /**
     * Writes a lesson with a raw `youtube_url`, bypassing every sanitizing
     * write path, and returns its id.
     */
    private function legacyLesson(?string $youtubeUrl): int
    {
        $lesson = Lesson::factory()->for($this->module)->create(['is_published' => true]);

        DB::table('lessons')->where('id', $lesson->id)->update(['youtube_url' => $youtubeUrl]);

        return $lesson->id;
    }

    private function storedUrl(int $lessonId): ?string
    {
        return DB::table('lessons')->where('id', $lessonId)->value('youtube_url');
    }

    public function test_it_migrates_legacy_watch_and_short_urls_to_embed_form(): void
    {
        $watch = $this->legacyLesson('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $watchNoWww = $this->legacyLesson('https://youtube.com/watch?v=9bZkp7q19f0');
        $shortLink = $this->legacyLesson('https://youtu.be/kJQP7kiw5Fk');
        $watchWithExtraParams = $this->legacyLesson('https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s');

        $this->migration()->up();

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $this->storedUrl($watch));
        $this->assertSame('https://www.youtube.com/embed/9bZkp7q19f0', $this->storedUrl($watchNoWww));
        $this->assertSame('https://www.youtube.com/embed/kJQP7kiw5Fk', $this->storedUrl($shortLink));
        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $this->storedUrl($watchWithExtraParams));
    }

    public function test_it_leaves_already_sanitized_urls_untouched(): void
    {
        $canonical = $this->legacyLesson('https://www.youtube.com/embed/dQw4w9WgXcQ');

        $this->migration()->up();

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $this->storedUrl($canonical));
    }

    public function test_it_leaves_null_and_empty_urls_untouched(): void
    {
        $null = $this->legacyLesson(null);
        $empty = $this->legacyLesson('');

        $this->migration()->up();

        $this->assertNull($this->storedUrl($null));
        $this->assertSame('', $this->storedUrl($empty));
    }

    public function test_it_leaves_unrecognizable_urls_intact_instead_of_nulling_them(): void
    {
        $vimeo = $this->legacyLesson('https://vimeo.com/123456789');
        $typo = $this->legacyLesson('https://www.youtube.com/watch?v=short');
        $lookAlikeHost = $this->legacyLesson('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ');
        $garbage = $this->legacyLesson('not a url at all');

        $this->migration()->up();

        $this->assertSame('https://vimeo.com/123456789', $this->storedUrl($vimeo));
        $this->assertSame('https://www.youtube.com/watch?v=short', $this->storedUrl($typo));
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $this->storedUrl($lookAlikeHost));
        $this->assertSame('not a url at all', $this->storedUrl($garbage));
    }

    public function test_it_is_idempotent_when_run_twice(): void
    {
        $watch = $this->legacyLesson('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $vimeo = $this->legacyLesson('https://vimeo.com/123456789');

        $this->migration()->up();
        $this->migration()->up();

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $this->storedUrl($watch));
        $this->assertSame('https://vimeo.com/123456789', $this->storedUrl($vimeo));
    }

    public function test_it_also_normalizes_soft_deleted_lessons(): void
    {
        $trashed = $this->legacyLesson('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        Lesson::query()->whereKey($trashed)->delete();

        $this->migration()->up();

        $this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $this->storedUrl($trashed));
    }
}
