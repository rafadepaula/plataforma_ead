<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * thrown by the `OrgScope` trait's `booted::creating` hook
 * when an org-scoped model is being created and neither the acting user's
 * `org_id` nor `session('active_org_id')` can resolve a tenant. Must never
 * surface as a raw 500 — mapped globally in `bootstrap/app.php` to an HTTP
 * 422 response (see `tenancy-conventions` skill).
 */
class UnresolvedOrgContextException extends RuntimeException
{
    //
}
