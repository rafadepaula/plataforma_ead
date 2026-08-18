<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidYoutubeUrlException;
use App\Services\YoutubeSanitizerService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 *  `YoutubeSanitizerService` validates a genuine `youtube.com`/
 * `youtu.be` URL, extracts the 11-char video ID, and returns the canonical
 * `https://www.youtube.com/embed/{id}` form. Anything else — malformed
 * URLs, non-YouTube domains, XSS/embed-injection attempts — must be
 * rejected via `InvalidYoutubeUrlException`.
 */
class YoutubeSanitizerServiceTest extends TestCase
{
    private YoutubeSanitizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new YoutubeSanitizerService;
    }

    #[DataProvider('validUrlsProvider')]
    public function test_it_sanitizes_valid_youtube_urls_to_the_canonical_embed_form(string $input): void
    {
        $this->assertSame(
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            $this->service->sanitize($input)
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validUrlsProvider(): array
    {
        return [
            'watch url' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch url without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch url with extra query params' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=30s'],
            'short url' => ['https://youtu.be/dQw4w9WgXcQ'],
            'already-embed url' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('invalidUrlsProvider')]
    public function test_it_rejects_non_youtube_or_malformed_urls(string $input): void
    {
        $this->expectException(InvalidYoutubeUrlException::class);

        $this->service->sanitize($input);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidUrlsProvider(): array
    {
        return [
            'non-youtube domain' => ['https://vimeo.com/12345'],
            'youtube-nocookie domain' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'javascript uri' => ['javascript:alert(1)'],
            'html injection attempt' => ['"><script>alert(1)</script>'],
            'short id (not 11 chars)' => ['https://youtu.be/short'],
            'empty string' => [''],
            'plain text' => ['not a url at all'],
        ];
    }
}
