<?php

namespace Tests\Unit\Services;

use App\Exceptions\InvalidVimeoUrlException;
use App\Services\VimeoSanitizerService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 *  `VimeoSanitizerService` validates a genuine `vimeo.com`/
 * `player.vimeo.com` URL — including the unlisted-video forms
 * (`vimeo.com/{id}/{hash}` path and `?h={hash}` query) — extracts the
 * numeric video id, and returns the canonical
 * `https://player.vimeo.com/video/{id}` form, carrying `?h={hash}` when
 * the input points at an unlisted video. Anything else — malformed URLs,
 * non-Vimeo domains, XSS/embed-injection attempts — must be rejected via
 * `InvalidVimeoUrlException`.
 */
class VimeoSanitizerServiceTest extends TestCase
{
    private VimeoSanitizerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VimeoSanitizerService;
    }

    #[DataProvider('validUrlsProvider')]
    public function test_it_sanitizes_valid_vimeo_urls_to_the_canonical_embed_form(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->service->sanitize($input));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function validUrlsProvider(): array
    {
        return [
            'plain url' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'plain url with www' => ['https://www.vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'player url' => ['https://player.vimeo.com/video/76979871', 'https://player.vimeo.com/video/76979871'],
            'player url with extra params' => [
                'https://player.vimeo.com/video/76979871?title=0&byline=0',
                'https://player.vimeo.com/video/76979871',
            ],
            'unlisted url with path hash' => [
                'https://vimeo.com/76979871/abcdef12345',
                'https://player.vimeo.com/video/76979871?h=abcdef12345',
            ],
            'player url with hash query' => [
                'https://player.vimeo.com/video/76979871?h=abcdef12345',
                'https://player.vimeo.com/video/76979871?h=abcdef12345',
            ],
            'player url with hash query and extra params' => [
                'https://player.vimeo.com/video/76979871?h=abcdef12345&title=0',
                'https://player.vimeo.com/video/76979871?h=abcdef12345',
            ],
        ];
    }

    public function test_it_extracts_the_video_id_from_every_supported_form(): void
    {
        $this->assertSame('76979871', $this->service->extractVideoId('https://vimeo.com/76979871'));
        $this->assertSame('76979871', $this->service->extractVideoId('https://player.vimeo.com/video/76979871?h=abc123'));
        $this->assertSame('76979871', $this->service->extractVideoId('https://vimeo.com/76979871/abc123'));
        $this->assertNull($this->service->extractVideoId('https://vimeo.com/channels/opensource'));
        $this->assertNull($this->service->extractVideoId(null));
        $this->assertNull($this->service->extractVideoId('   '));
    }

    #[DataProvider('invalidUrlsProvider')]
    public function test_it_rejects_non_vimeo_or_malformed_urls(string $input): void
    {
        $this->expectException(InvalidVimeoUrlException::class);

        $this->service->sanitize($input);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function invalidUrlsProvider(): array
    {
        return [
            'non-vimeo domain' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtube-nocookie domain' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'vimeo channel path' => ['https://vimeo.com/channels/opensource'],
            'vimeo showcase path' => ['https://vimeo.com/showcase/1234567'],
            'id too short' => ['https://vimeo.com/12345'],
            'javascript uri' => ['javascript:alert(1)'],
            'html injection attempt' => ['"><script>alert(1)</script>'],
            'empty string' => [''],
            'plain text' => ['not a url at all'],
        ];
    }
}
