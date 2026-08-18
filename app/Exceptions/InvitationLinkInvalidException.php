<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * thrown by `InvitationController@show`/`@store` when a
 * `/convite/{token}` link cannot be resolved to a usable
 * `InvitationLink` (not found, expired, exhausted, or revoked). Must never
 * surface as a raw 500 — mapped globally in `bootstrap/app.php` to a
 * content-negotiated response, the same pattern used for
 * `CourseHasActiveEnrollmentsException`/`UnresolvedOrgContextException`.
 */
class InvitationLinkInvalidException extends RuntimeException
{
    //
}
