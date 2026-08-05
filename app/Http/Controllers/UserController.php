<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * RF04 — Aluno/Gestor CRUD, scoped to the acting user's tenant context.
 * `org_id` is always resolved server-side via {@see self::resolveOrgId()}
 * (the acting Gestor's own `org_id`, or the impersonating Admin's
 * `session('active_org_id')`) and is never accepted from request input —
 * mirroring the `OrgScope::booted()` creating-hook pattern, even though
 * `User` itself intentionally does not use the `OrgScope` trait.
 */
class UserController extends Controller
{
    use ResolvesOrgContext;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $orgId = $this->resolveOrgId($request);

        $users = User::query()
            ->where('org_id', $orgId)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [
                RolesEnum::ALUNO->value,
                RolesEnum::GESTOR->value,
            ]))
            ->orderBy('name')
            ->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        return view('users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $orgId = $this->resolveOrgId($request);
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $data['org_id'] = $orgId;
        $data['password'] = Hash::make($data['password']);
        $data['status'] = 'active';

        $user = User::create($data);
        $user->assignRole($role);

        return redirect()->route('users.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role'], $data['org_id']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $oldStatus = $user->getOriginal('status');

        $user->update($data);
        $user->syncRoles([$role]);

        // SPEC-15 §3 — `user.status_changed` is a critical-action event
        // distinct from `AuditableTrait`'s generic `user.updated` mutation
        // row (which already fires on every `update()` call); it is only
        // recorded when `status` actually changed, not on every edit.
        if (array_key_exists('status', $data) && $data['status'] !== $oldStatus) {
            try {
                AuditService::log(
                    event: 'user.status_changed',
                    orgId: $user->org_id ? (int) $user->org_id : null,
                    userId: Auth::id(),
                    payload: [
                        'user_id' => $user->id,
                        'old_status' => $oldStatus,
                        'new_status' => $data['status'],
                        'reason' => $request->input('reason'),
                    ],
                );
            } catch (Throwable $e) {
                report($e);
            }
        }

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', 'Usuário removido com sucesso.');
    }

    /**
     * @see ResolvesOrgContext::resolveOrgId()
     */
    protected function orgContextAction(): string
    {
        return 'gerenciar usuários';
    }
}
