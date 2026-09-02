<?php

namespace Tests\Unit\Models;

use App\Models\Lesson;
use Tests\TestCase;

/**
 *  Exercises the provider-agnostic video accessors of `Lesson` against
 * both supported providers: `video_provider` (stored column plus the
 * drift fallback that detects the provider from the URL itself),
 * `video_id`/`video_embed_url` (resolved through the sanitizer registry)
 * and the `hasPlayableVideo()` predicate the 422 shape guards share with
 * the dispatch view. The deprecated `youtube_video_id`/
 * `youtube_embed_url` aliases must keep delegating to the new accessors.
 */
class LessonVideoAccessorsTest extends TestCase
{
    public function test_it_resolves_a_youtube_lesson_id_and_embed_url(): void
    {
        $lesson = Lesson::factory()->withYoutube()->make(['video_url' => 'https://youtu.be/dQw4w9WgXcQ']);

        $this->assertSame('youtube', $lesson->video_provider);
        $this->assertSame('dQw4w9WgXcQ', $lesson->video_id);
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $lesson->video_embed_url);
        $this->assertTrue($lesson->hasPlayableVideo());
    }

    public function test_it_resolves_a_vimeo_lesson_id_and_embed_url(): void
    {
        $lesson = Lesson::factory()->withVimeo()->make(['video_url' => 'https://vimeo.com/76979871']);

        $this->assertSame('vimeo', $lesson->video_provider);
        $this->assertSame('76979871', $lesson->video_id);
        $this->assertSame('https://player.vimeo.com/video/76979871', $lesson->video_embed_url);
        $this->assertTrue($lesson->hasPlayableVideo());
    }

    public function test_it_keeps_the_unlisted_hash_on_the_vimeo_embed_url(): void
    {
        $lesson = Lesson::factory()->withVimeo()->make([
            'video_url' => 'https://vimeo.com/76979871/abcdef12345',
        ]);

        $this->assertSame('76979871', $lesson->video_id);
        $this->assertSame('https://player.vimeo.com/video/76979871?h=abcdef12345', $lesson->video_embed_url);
    }

    public function test_an_unparseable_url_has_no_player_and_degrades(): void
    {
        $lesson = Lesson::factory()->withYoutube()->make(['video_url' => 'not a url at all']);

        $this->assertNull($lesson->video_id);
        $this->assertNull($lesson->video_embed_url);
        $this->assertFalse($lesson->hasPlayableVideo());
    }

    public function test_a_lesson_without_video_resolves_to_null_provider(): void
    {
        $lesson = Lesson::factory()->richText()->make();

        $this->assertNull($lesson->video_provider);
        $this->assertNull($lesson->video_id);
        $this->assertNull($lesson->video_embed_url);
        $this->assertFalse($lesson->hasPlayableVideo());
    }

    public function test_video_provider_falls_back_to_url_detection_for_rows_saved_without_the_column(): void
    {
        $lesson = Lesson::factory()->make([
            'video_provider' => null,
            'video_url' => 'https://player.vimeo.com/video/76979871?h=abc123',
        ]);

        $this->assertSame('vimeo', $lesson->video_provider);
        $this->assertSame('76979871', $lesson->video_id);
        $this->assertTrue($lesson->hasPlayableVideo());
    }

    public function test_deprecated_youtube_aliases_delegate_to_the_provider_agnostic_accessors(): void
    {
        $lesson = Lesson::factory()->withYoutube()->make();

        $this->assertSame($lesson->video_id, $lesson->youtube_video_id);
        $this->assertSame($lesson->video_embed_url, $lesson->youtube_embed_url);
    }

    public function test_pending_glyph_marks_a_video_lesson_as_play(): void
    {
        $this->assertSame('play', Lesson::factory()->withVimeo()->make()->pending_glyph);
        $this->assertSame('book-open', Lesson::factory()->richText()->make()->pending_glyph);
    }
}
