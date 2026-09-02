<?php

namespace App\Exceptions;

use App\Services\VimeoSanitizerService;

/**
 * thrown by the Vimeo half of the sanitizer registry
 * ({@see VimeoSanitizerService}) when the given URL is not
 * a genuine `vimeo.com`/`player.vimeo.com` link with an extractable
 * numeric video id (malformed URLs, non-Vimeo domains, and
 * XSS/embed-injection attempts such as `javascript:` URIs all fail the
 * same way). Extends the generic {@see InvalidVideoUrlException} so the
 * Lesson Form Request's `withValidator()` can catch every provider's
 * failure with one catch block.
 */
class InvalidVimeoUrlException extends InvalidVideoUrlException
{
    //
}
