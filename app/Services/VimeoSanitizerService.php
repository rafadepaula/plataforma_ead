<?php

namespace App\Services;

use App\Exceptions\InvalidVimeoUrlException;

/**
 * Vimeo counterpart of {@see YoutubeSanitizerService}: validates a
 * Lesson's `video_url` input is a genuine `vimeo.com`/`player.vimeo.com`
 * link and rewrites it to the canonical `https://player.vimeo.com/video/{id}`
 * form — carrying `?h={hash}` when the input points at an UNLISTED video
 * (`vimeo.com/{id}/{hash}` path form or an `h=` query parameter), which is
 * how semi-private content is published on Vimeo. Like the YouTube
 * sanitizer, it is deliberately restricted to those two host shapes only —
 * anything that doesn't match is treated as an XSS/embed-injection attempt
 * (`javascript:` URIs, arbitrary `<iframe>` src values, ...) and rejected
 * the same way as a simple typo.
 */
class VimeoSanitizerService implements VideoUrlSanitizer
{
    /**
     * Matches (with optional `www.` and a trailing query string):
     * - https://vimeo.com/{numeric id}
     * - https://vimeo.com/{numeric id}/{unlisted hash}
     * - https://player.vimeo.com/video/{numeric id}
     */
    private const PATTERN = '#^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:video/)?(\d{6,})(?:/([A-Za-z0-9]+))?(?:[?&][^\s]*)?$#i';

    public function sanitize(string $url): string
    {
        $parts = $this->resolve(trim($url));

        if ($parts === null) {
            throw new InvalidVimeoUrlException('URL do Vimeo inválida ou não suportada: "'.trim($url).'".');
        }

        return $this->embedUrl($parts['id'], $parts['hash']);
    }

    public function extractVideoId(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = $this->resolve(trim($url));

        return $parts === null ? null : $parts['id'];
    }

    public function tryCanonicalize(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $parts = $this->resolve(trim($url));

        return $parts === null ? null : $this->embedUrl($parts['id'], $parts['hash']);
    }

    /**
     * The embeddable `player.vimeo.com` URL; the `h={hash}` query keeps an
     * unlisted video playable — without it the player renders a private
     * placeholder instead of the video.
     */
    private function embedUrl(string $id, ?string $hash): string
    {
        return $hash === null
            ? "https://player.vimeo.com/video/{$id}"
            : "https://player.vimeo.com/video/{$id}?h={$hash}";
    }

    /**
     * The numeric video id plus the unlisted-video hash — taken from the
     * `/{hash}` path segment when present, else from the `h=` query
     * parameter (the `player.vimeo.com` embed form).
     *
     * @return array{id: string, hash: ?string}|null
     */
    private function resolve(string $url): ?array
    {
        if (! preg_match(self::PATTERN, $url, $matches)) {
            return null;
        }

        $hash = $matches[2] ?? null;

        if ($hash === null) {
            parse_str((string) (parse_url($url, PHP_URL_QUERY) ?? ''), $query);
            $candidate = $query['h'] ?? null;
            $hash = is_string($candidate) && $candidate !== '' ? $candidate : null;
        }

        return ['id' => $matches[1], 'hash' => $hash];
    }
}
