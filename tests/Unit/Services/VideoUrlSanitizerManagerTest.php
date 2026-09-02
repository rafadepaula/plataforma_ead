<?php

namespace Tests\Unit\Services;

use App\Services\VideoUrlSanitizer;
use App\Services\VideoUrlSanitizerManager;
use App\Services\VimeoSanitizerService;
use App\Services\YoutubeSanitizerService;
use InvalidArgumentException;
use Tests\TestCase;

/**
 *  `VideoUrlSanitizerManager` is the single place that knows every
 * supported video provider: it resolves the sanitizer of a stored
 * `video_provider` value and detects which provider a raw URL belongs to
 * (the drift fallback used by `Lesson::videoProvider` and the form's
 * provider-less validation).
 */
class VideoUrlSanitizerManagerTest extends TestCase
{
    private VideoUrlSanitizerManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new VideoUrlSanitizerManager;
    }

    public function test_it_resolves_each_known_provider_sanitizer(): void
    {
        $this->assertInstanceOf(YoutubeSanitizerService::class, $this->manager->for('youtube'));
        $this->assertInstanceOf(VimeoSanitizerService::class, $this->manager->for('vimeo'));
        $this->assertInstanceOf(VideoUrlSanitizer::class, $this->manager->for('youtube'));
    }

    public function test_it_rejects_unknown_providers(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->manager->for('dailymotion');
    }

    public function test_provider_for_detects_the_provider_of_a_raw_url(): void
    {
        $this->assertSame('youtube', $this->manager->providerFor('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('youtube', $this->manager->providerFor('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('vimeo', $this->manager->providerFor('https://vimeo.com/76979871'));
        $this->assertSame('vimeo', $this->manager->providerFor('https://player.vimeo.com/video/76979871?h=abc123'));
    }

    public function test_provider_for_returns_null_for_unrecognized_values(): void
    {
        $this->assertNull($this->manager->providerFor('https://example.com/video/1'));
        $this->assertNull($this->manager->providerFor('not a url at all'));
        $this->assertNull($this->manager->providerFor(null));
        $this->assertNull($this->manager->providerFor(''));
    }

    public function test_providers_constant_drives_the_form_validation_rule(): void
    {
        $this->assertSame(['youtube', 'vimeo'], VideoUrlSanitizerManager::PROVIDERS);
    }
}
