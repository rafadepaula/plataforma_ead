<?php

use App\Exceptions\CourseHasActiveEnrollmentsException;
use App\Exceptions\InvitationLinkInvalidException;
use App\Exceptions\UnresolvedOrgContextException;
use App\Http\Middleware\EnsureStudentIsEnrolled;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPEC-18 RF34/RN14 — required for `Auth::logoutOtherDevices()`
        // (see `PasswordController`) to actually have an effect: this
        // appends `Illuminate\Session\Middleware\AuthenticateSession` to
        // the `web` group, which compares the session's cached password
        // hash against the DB on every request and force-logs-out any
        // session left stale by a password change.
        $middleware->authenticateSessions();

        // SPEC-00 §4 — `spatie/laravel-permission`'s route middleware
        // aliases, matched against `RolesEnum` values (see the
        // `tenancy-conventions` skill for usage).
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            // SPEC-07 RF20 — gates the student-facing classroom/lesson
            // /progress routes behind an active/completed enrollment.
            'student.enrolled' => EnsureStudentIsEnrolled::class,
            // BUG-001 — custom role-aware guest redirect (replaces the
            // framework default that falls back to `/` when no `home`
            // route exists).
            'guest' => RedirectIfAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // SPEC-06 — `request->expectsJson()` is included alongside the
        // `api/*` prefix check so AJAX callers on ordinary web routes
        // (e.g. `/convite/check-email`, `postJson()` calls against the
        // Gestor panel's `courses.enrollments.store`) get a JSON 422/4xx
        // body instead of Laravel's default redirect-back-with-flashed
        // -errors behavior.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // SPEC-00 §3 — an org-scoped model created with no resolvable
        // org_id (e.g. an Admin without an active Impersonate Org) must
        // never surface as a raw 500. Content-negotiate: JSON/AJAX callers
        // get a 422 body, web callers get a plain redirect-back (302) with
        // a flashed error message.
        $exceptions->render(function (UnresolvedOrgContextException $e, Request $request) {
            $message = 'Selecione uma Organização ativa antes de continuar.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withInput()->with('error', $message);
        });

        // SPEC-05 — a Course with at least one `active` `course_user`
        // enrollment must never be soft-deleted out from under enrolled
        // students. Same content-negotiation pattern as
        // `UnresolvedOrgContextException` above.
        $exceptions->render(function (CourseHasActiveEnrollmentsException $e, Request $request) {
            $message = 'Não é possível excluir um Curso com matrículas ativas.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        });

        // SPEC-06 RF03 — a `/convite/{token}` that cannot be resolved to a
        // usable `InvitationLink` (not found/expired/exhausted/revoked)
        // must never surface as a raw 404/500. Same content-negotiation
        // pattern as `CourseHasActiveEnrollmentsException` above.
        $exceptions->render(function (InvitationLinkInvalidException $e, Request $request) {
            $message = 'Este link de convite é inválido, expirou ou já atingiu o limite de usos.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 404);
            }

            return response($message, 404);
        });

        // SPEC-00 §5 — a `role:`-gated route hit by a guest (no
        // authenticated user at all) should redirect to login rather than
        // surface Spatie's default 403 (which is reserved for an
        // authenticated user missing the required role).
        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if (Auth::guest()) {
                return redirect()->guest(Route::has('login') ? route('login') : '/login');
            }
        });
    })->create();
