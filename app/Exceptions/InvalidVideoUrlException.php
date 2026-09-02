<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Base exception for every video-URL sanitizer failure: the given URL is
 * not a genuine link of the expected provider with an extractable video
 * id (malformed URLs, foreign domains, and XSS/embed-injection attempts
 * such as `javascript:` URIs all fail the same way). Extends
 * `\InvalidArgumentException` so it is caught inside the Lesson Form
 * Request's `withValidator()` and re-thrown as a normal validation
 * failure on the `video_url` field — it never needs a global exception
 * handler entry. The provider-specific subclasses
 * (`InvalidYoutubeUrlException`/`InvalidVimeoUrlException`) only differ
 * in the provider named in their messages.
 */
class InvalidVideoUrlException extends InvalidArgumentException
{
    //
}
