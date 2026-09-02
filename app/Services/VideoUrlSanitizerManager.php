<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Registry that resolves the {@see VideoUrlSanitizer} of a Lesson's
 * `video_provider` and detects which provider a raw URL belongs to. The
 * single place that knows every supported provider — adding a new one is
 * a new sanitizer class plus one entry here, nothing else.
 */
class VideoUrlSanitizerManager
{
    /**
     * The provider values accepted by the Lesson Form's `video_provider`
     * select and stored in the `lessons.video_provider` column.
     *
     * @var list<string>
     */
    public const PROVIDERS = ['youtube', 'vimeo'];

    /**
     * @var array<string, class-string<VideoUrlSanitizer>>
     */
    private const SANITIZERS = [
        'youtube' => YoutubeSanitizerService::class,
        'vimeo' => VimeoSanitizerService::class,
    ];

    /**
     * The sanitizer of one provider.
     *
     * @param  string  $provider  one of {@see PROVIDERS}
     */
    public function for(string $provider): VideoUrlSanitizer
    {
        $sanitizer = self::SANITIZERS[$provider] ?? null;

        if ($sanitizer === null) {
            throw new InvalidArgumentException("Provedor de vídeo desconhecido: \"{$provider}\".");
        }

        return app($sanitizer);
    }

    /**
     * Detects which provider a raw URL belongs to, independent of any
     * stored `video_provider` — the drift fallback for rows saved without
     * (or before) the column, and the guess behind the form's
     * provider-less URL validation. `null` when no sanitizer recognizes
     * the value.
     */
    public function providerFor(?string $url): ?string
    {
        foreach (self::SANITIZERS as $provider => $sanitizer) {
            if (app($sanitizer)->extractVideoId($url) !== null) {
                return $provider;
            }
        }

        return null;
    }
}
