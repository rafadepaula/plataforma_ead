<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\UserHasCreatedInvitationLinksException;
use App\Exceptions\UserHasIssuedCertificatesException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserAdminRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * cross-org, all-roles administration screen at `admin/users`,
 * reserved to `role:admin` (see `routes/web.php`). Deliberately does NOT
 * extend `UserController` and does NOT use the `ResolvesOrgContext` trait:
 * the listing is global by definition, no `org_id` is ever resolved from
 * the acting Admin — every tenant boundary that would otherwise apply is
 * either absent (Admin is global) or comes straight from the request's
 * own filters (`org_id` query param, not session state).
 *
 * `UserPolicy`'s existing `sharesOrgContext()`-driven abilities
 * (`viewAny`/`view`/`update`/`delete`) — used by the operational
 * `users.*` screen — are untouched; this controller exclusively uses the
 * `*Global` abilities added alongside them.
 */
class UserAdminController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAnyGlobal', User::class);

        $users = User::query()
            ->with(['organization', 'roles'])
            ->when($request->filled('name'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('name')->toString().'%'))
            ->when($request->filled('email'), fn (Builder $query) => $query->where('email', 'like', '%'.$request->string('email')->toString().'%'))
            ->when($request->filled('org_id'), fn (Builder $query) => $query->where('org_id', (int) $request->query('org_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('role'), fn (Builder $query) => $query->whereHas(
                'roles',
                fn (Builder $roles) => $roles->where('name', $request->string('role')->toString())
            ))
            ->when($request->filled('created_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->string('created_from')->toString()))
            ->when($request->filled('created_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->string('created_to')->toString()))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'organizations' => Organization::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(User $user): View
    {
        Gate::authorize('viewGlobal', $user);

        $user->load(['organization', 'roles']);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('updateGlobal', $user);

        $user->load('roles');

        return view('admin.users.edit', [
            'user' => $user,
            'organizations' => Organization::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function update(UpdateUserAdminRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if (($data['status'] ?? null) === 'inactive' && $user->id === Auth::id()) {
            abort(403, 'Você não pode desativar sua própria conta.');
        }

        if ($role !== 'admin' && $user->id === Auth::id()) {
            abort(403, 'Você não pode remover seu próprio papel de administrador.');
        }

        $oldStatus = $user->getOriginal('status');

        $user->update($data);
        $user->syncRoles([$role]);

        $this->auditStatusChangeIfNeeded($request, $user, $oldStatus, $data['status'] ?? $oldStatus);

        return redirect()->route('admin.users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    /**
     * Status-only PATCH used by the listing's ativar/desativar row
     * actions (`admin.users.status`). Kept separate from `update()` so a
     * single-field toggle from the table doesn't require posting the
     * entire profile form.
     */
    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('updateGlobal', $user);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['status'] === 'inactive' && $user->id === Auth::id()) {
            abort(403, 'Você não pode desativar sua própria conta.');
        }

        $oldStatus = $user->getOriginal('status');

        $user->update(['status' => $data['status']]);

        $this->auditStatusChangeIfNeeded($request, $user, $oldStatus, $data['status']);

        return redirect()->route('admin.users.index')->with('success', 'Status do usuário atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deleteGlobal', $user);

        // `certificates.user_id` is `ON DELETE RESTRICT` and `users` has no
        // `deleted_at` — a hard delete of a User with issued certificates
        // would otherwise crash with a raw 500 QueryException.
        if ($user->certificates()->exists()) {
            throw new UserHasIssuedCertificatesException(
                "Usuário #{$user->id} possui certificados emitidos e não pode ser excluído."
            );
        }

        // `invitation_links.created_by` is also `ON DELETE RESTRICT`
        // . `InvitationLink` is directly org-scoped (`OrgScope`),
        // so the check must bypass the `org` global scope — otherwise an
        // Admin impersonating a different Organization (or none at all)
        // would miss links the user created in other orgs and the delete
        // would still crash with a raw 500 QueryException.
        if ($user->createdInvitationLinks()->withoutGlobalScope('org')->exists()) {
            throw new UserHasCreatedInvitationLinksException(
                "Usuário #{$user->id} criou links de convite e não pode ser excluído."
            );
        }

        $oldStatus = $user->status;
        $orgId = $user->org_id ? (int) $user->org_id : null;
        $userId = $user->id;

        $user->delete();

        // reuses the existing `user.status_changed` critical
        // event for deletion too (RN — "reaproveitando o evento
        // user.status_changed já existente"), with a synthetic
        // `new_status: 'deleted'` payload since there is no dedicated
        // deletion event today.
        try {
            AuditService::log(
                event: 'user.status_changed',
                orgId: $orgId,
                userId: Auth::id(),
                payload: [
                    'user_id' => $userId,
                    'old_status' => $oldStatus,
                    'new_status' => 'deleted',
                    'reason' => $request->input('reason'),
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('admin.users.index')->with('success', 'Usuário removido com sucesso.');
    }

    /**
     * `user.status_changed` is a critical-action event
     * distinct from `AuditableTrait`'s generic `user.updated` mutation
     * row; it is only recorded when `status` actually changed. Mirrors
     * `UserController::update()`'s audit block exactly.
     */
    private function auditStatusChangeIfNeeded(Request $request, User $user, ?string $oldStatus, ?string $newStatus): void
    {
        if ($newStatus === null || $newStatus === $oldStatus) {
            return;
        }

        try {
            AuditService::log(
                event: 'user.status_changed',
                orgId: $user->org_id ? (int) $user->org_id : null,
                userId: Auth::id(),
                payload: [
                    'user_id' => $user->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'reason' => $request->input('reason'),
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
