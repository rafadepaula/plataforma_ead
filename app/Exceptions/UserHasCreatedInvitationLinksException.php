<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * thrown by the global Admin user-management delete guard
 * (`UserAdminController@destroy`) when an Admin attempts to hard-delete a
 * `User` who still owns at least one `InvitationLink` row (as its
 * `created_by`). `users` has no `deleted_at` , so `destroy()`
 * is a hard delete, and `invitation_links.created_by` is `ON DELETE
 * RESTRICT`  — without this guard the delete would surface as a
 * raw 500 `QueryException`. Must never do that — mapped globally in
 * `bootstrap/app.php` to a 422/redirect response, the same pattern used
 * for `UserHasIssuedCertificatesException`.
 */
class UserHasCreatedInvitationLinksException extends RuntimeException
{
    //
}
