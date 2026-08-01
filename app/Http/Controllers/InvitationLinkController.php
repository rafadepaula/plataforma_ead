<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvitationLinkRequest;
use App\Models\Course;
use App\Models\InvitationLink;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * SPEC-06 RF03 — Gestor/Admin panel for generating and revoking a
 * Course's `/convite/{token}` invitation links, nested under `{course}`
 * (shallow — `index`/`create`/`store` reached via `{course}`, `destroy`
 * via `{invitation_link}` alone). `Course`'s own `OrgScope` already keeps
 * route-model-bound `{course}` confined to the acting Gestor's tenant, so
 * no manual `org_id` filtering is done here (mirrors `CourseController`).
 */
class InvitationLinkController extends Controller
{
    public function index(Course $course): View
    {
        Gate::authorize('viewAny', [InvitationLink::class, $course]);

        $invitationLinks = $course->invitationLinks()->latest()->paginate(15);

        // Every row belongs to this exact, already-loaded `$course` (they
        // were fetched via `$course->invitationLinks()`), so hydrate the
        // `course` relation from it directly instead of letting
        // `InvitationLink::courseIsAvailable()` issue a fresh `SELECT`
        // per row — avoids an N+1 across the paginated page.
        $invitationLinks->getCollection()->each(
            fn (InvitationLink $invitationLink) => $invitationLink->setRelation('course', $course)
        );

        return view('courses.invitation-links.index', ['course' => $course, 'invitationLinks' => $invitationLinks]);
    }

    public function create(Course $course): View
    {
        Gate::authorize('create', [InvitationLink::class, $course]);

        return view('courses.invitation-links.create', ['course' => $course]);
    }

    public function store(StoreInvitationLinkRequest $request, Course $course): RedirectResponse
    {
        $course->invitationLinks()->create([
            ...$request->validated(),
            'token' => Str::random(64),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('courses.invitation-links.index', $course)
            ->with('success', 'Link de convite criado com sucesso.');
    }

    /**
     * Link-level revocation (RF03) — sets `revoked_at` rather than
     * deleting the row, so already-consumed enrollments and audit history
     * remain intact; distinct from `EnrollmentController::destroy()`'s
     * per-enrollment `course_user.status = 'cancelled'` (RF21).
     */
    public function destroy(InvitationLink $invitationLink): RedirectResponse
    {
        Gate::authorize('delete', $invitationLink);

        // Fetch without global scopes (mirrors `InvitationLinkPolicy::delete()`)
        // so a soft-deleted parent `Course` still resolves here instead of
        // leaving `$course` null and crashing the redirect below.
        $course = $invitationLink->course()->withoutGlobalScopes()->firstOrFail();
        $invitationLink->update(['revoked_at' => now()]);

        return redirect()->route('courses.invitation-links.index', $course)
            ->with('success', 'Link de convite revogado com sucesso.');
    }
}
