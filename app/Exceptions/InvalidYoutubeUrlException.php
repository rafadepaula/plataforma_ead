<?php

namespace App\Exceptions;

/**
 * thrown by `YoutubeSanitizerService::sanitize()` when the given
 * URL is not a genuine `youtube.com`/`youtu.be` link with an extractable
 * 11-character video ID (malformed URLs, non-YouTube domains, and
 * XSS/embed-injection attempts such as `javascript:` URIs all fail the
 * same way). Extends the generic {@see InvalidVideoUrlException} so the
 * Lesson Form Request's `withValidator()` can catch every provider's
 * failure with one catch block — it never needs a global exception
 * handler entry.
 */
class InvalidYoutubeUrlException extends InvalidVideoUrlException
{
    //
}
