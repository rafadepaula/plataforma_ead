<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * thrown by `YoutubeSanitizerService::sanitize()` when the given
 * URL is not a genuine `youtube.com`/`youtu.be` link with an extractable
 * 11-character video ID (malformed URLs, non-YouTube domains, and
 * XSS/embed-injection attempts such as `javascript:` URIs all fail the
 * same way). Extends `\InvalidArgumentException` so it is caught inside
 * the Lesson Form Request's `withValidator()` and re-thrown as a normal
 * validation failure on the `youtube_url` field — it never needs a global
 * exception handler entry.
 */
class InvalidYoutubeUrlException extends InvalidArgumentException
{
    //
}
