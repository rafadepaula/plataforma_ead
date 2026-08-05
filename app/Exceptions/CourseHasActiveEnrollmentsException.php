<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * SPEC-05 — thrown by the Course delete guard (`CourseController@destroy`)
 * when a Gestor/Admin attempts to soft-delete a `Course` that still has at
 * least one `active` `course_user` enrollment. Must never surface as a raw
 * 500 — mapped globally in `bootstrap/app.php` to an HTTP 422 response
 * (see `courses-conventions` skill), the same pattern used for
 * `UnresolvedOrgContextException`.
 */
class CourseHasActiveEnrollmentsException extends RuntimeException
{
    //
}
