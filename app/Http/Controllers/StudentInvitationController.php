<?php

namespace App\Http\Controllers;

use App\Models\StudentInvitation;
use App\Models\User;
use App\Services\OrgContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The Gestor's side of the per-student unique invitation: issue (get
 * OR create, so the "Copiar convite" button is idempotent), regenerate
 * (revoke the live token and issue another — rotation when a link leaks)
 * and revoke. Every invitation finalizes exactly one pre-registered
 * Aluno's account in the acting Organization, so authorization is the
 * same boundary as the student directory itself:
 * `UserPolicy::updateStudent` (gestor of the same org + target is an
 * Aluno holding an account there). Building the shareable URL with plain
 * `url()` is safe here: the Gestor is browsing their own portal's host,
 * the same assumption `OrgUrl` documents for non-queued contexts.
 */
class StudentInvitationController extends Controller
{
    /**
     * JSON: `{ url }` — the frontend copies it to the clipboard.
     */
    public function issue(User $user): JsonResponse
    {
        Gate::authorize('updateStudent', $user);

        $invitation = $this->existingUsableInvitation($user)
            ?? $this->createInvitation($user);

        return response()->json(['url' => url('/convite/'.$invitation->token)]);
    }

    /**
     * Rotation: the live token is revoked (a leaked link stops working
     * immediately) and a fresh one is issued in the same transaction, so
     * there is never a window with zero usable invitations. Web redirect
     * (confirm-modal form): the new link goes back in the flash banner,
     * same handoff as the enrollment screen.
     */
    public function regenerate(User $user): RedirectResponse
    {
        Gate::authorize('updateStudent', $user);

        $invitation = DB::transaction(function () use ($user): StudentInvitation {
            StudentInvitation::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->whereNull('used_at')
                ->update(['revoked_at' => now()]);

            return $this->createInvitation($user);
        });

        return back()
            ->with('success', 'Novo link de convite gerado. O anterior deixou de funcionar.')
            ->with('invitation_url', url('/convite/'.$invitation->token));
    }

    /**
     * Revoke without replacing — the Gestor decided this Aluno should not
     * finalize right now. Web redirect (form submit), not JSON.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('updateStudent', $user);

        StudentInvitation::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->whereNull('used_at')
            ->update(['revoked_at' => now()]);

        return back()->with('success', 'Link de convite revogado.');
    }

    /**
     * Org scoping comes from `OrgScope`'s global scope; this is the
     * idempotency half of `issue()`.
     */
    private function existingUsableInvitation(User $user): ?StudentInvitation
    {
        return StudentInvitation::query()
            ->where('user_id', $user->id)
            ->usable()
            ->first();
    }

    private function createInvitation(User $user): StudentInvitation
    {
        return $user->studentInvitations()->create([
            'org_id' => OrgContext::current()->orgId(),
            'token' => Str::random(64),
            'created_by' => auth()->id(),
        ]);
    }
}
