<?php

namespace App\Services;

use App\Exceptions\InvalidYoutubeUrlException;

/**
 * The YouTube half of the sanitizer registry: validates a Lesson's
 * `video_url` input is a genuine `youtube.com`/`youtu.be` link and
 * rewrites it to the canonical, privacy-enhanced
 * `https://www.youtube-nocookie.com/embed/{id}` form. The input pattern
 * also accepts the nocookie embed form itself — sanitize() output must
 * re-parse as a valid id, or every accessor built on `extractVideoId`
 * would degrade the lesson it just sanitized. Anything else (look-alike
 * hosts beyond those two shapes, `javascript:` URIs, arbitrary `<iframe>`
 * src values, ...) is treated as an XSS/embed-injection attempt and
 * rejected the same way as a simple typo.
 */
class YoutubeSanitizerService implements VideoUrlSanitizer
{
    /**
     * Matches (with optional `www.` and a trailing query string):
     * - https://www.youtube.com/watch?v={11-char id}
     * - https://www.youtube.com/embed/{11-char id}
     * - https://www.youtube-nocookie.com/embed/{11-char id}
     * - https://youtu.be/{11-char id}
     */
    private const PATTERN = '#^https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtube-nocookie\.com/embed/|youtu\.be/)([A-Za-z0-9_-]{11})(?:[&?][^\s]*)?$#i';

    public function sanitize(string $url): string
    {
        $url = trim($url);

        if (! preg_match(self::PATTERN, $url, $matches)) {
            throw new InvalidYoutubeUrlException("URL do YouTube inválida ou não suportada: \"{$url}\".");
        }

        return 'https://www.youtube-nocookie.com/embed/'.$matches[1];
    }

    /**
     *  non-throwing counterpart of `sanitize()`: returns the 11-char
     * video id for any supported form (`watch?v=`, `embed/`, `youtu.be/`), or
     * `null` when the value is empty/unrecognizable. Used by consumers that
     * must degrade gracefully (the classroom player) and by the data
     * normalization migration, which must leave unrecognizable rows intact
     * rather than blowing up mid-batch.
     */
    public function extractVideoId(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        if (! preg_match(self::PATTERN, trim($url), $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Non-throwing canonicalization: the `embed/{id}` URL for any supported
     * input, or `null` when the value cannot be resolved to a video id.
     */
    public function tryCanonicalize(?string $url): ?string
    {
        $videoId = $this->extractVideoId($url);

        return $videoId === null ? null : 'https://www.youtube-nocookie.com/embed/'.$videoId;
    }
}
