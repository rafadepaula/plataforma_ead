<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionOrgAccountAction;
use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UserHasCreatedInvitationLinksException;
use App\Exceptions\UserHasIssuedCertificatesException;
use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Organization;
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
 *  Aluno/Gestor CRUD, scoped to the acting user's tenant context.
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
            ->whereHas('credentials', fn ($query) => $query->where('org_id', $orgId))
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

    public function store(StoreUserRequest $request, ProvisionOrgAccountAction $provision): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $organization = Organization::findOrFail($this->resolveOrgId($request));

        // e-mail global já existente reusa a pessoa e cria a conta desta org
        $provision->execute(
            organization: $organization,
            name: $data['name'],
            email: $data['email'],
            cpf: $data['cpf'] ?? null,
            password: $data['password'],
            role: $role,
        );

        return redirect()->route('users.index')->with('success', 'Usuário criado com sucesso.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $orgId = $this->resolveOrgId($request);
        $data = $request->validated();
        $role = $data['role'];
        unset($data['role']);

        $credential = $user->credentials()->forOrg($orgId)->first();

        if (! $credential) {
            abort(404);
        }

        $oldStatus = $credential->getOriginal('status');

        $user->update(collect($data)->only(['name', 'email', 'cpf'])->all());
        $user->syncRoles([$role]);

        if (! empty($data['password'])) {
            $credential->forceFill(['password' => Hash::make($data['password'])])->save();
            // senha imposta invalida o "lembrar-me" desta conta
            $credential->rotateRememberToken();
        }

        // `user.status_changed` is a critical-action event distinct from
        // `AuditableTrait`'s generic mutation row; only recorded when the
        // account status actually changed.
        if (array_key_exists('status', $data) && $data['status'] !== $oldStatus) {
            $credential->forceFill(['status' => $data['status']])->save();

            if ($data['status'] === 'inactive') {
                $credential->rotateRememberToken();
            }

            try {
                AuditService::log(
                    event: 'user.status_changed',
                    orgId: $orgId,
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

        $orgId = $this->resolveOrgId($request);
        $credential = $user->credentials()->forOrg($orgId)->first();

        if (! $credential) {
            abort(404);
        }

        // Removing from THIS portal only: a person holding accounts in
        // other Organizations keeps living there — person identity is
        // global, accounts are per-org. The person row is hard-deleted
        // only when this was their last account (with the same
        // ON DELETE RESTRICT pre-flights as the global screen).
        if ($user->credentials()->count() > 1) {
            $credential->delete();

            return redirect()->route('users.index')->with('success', 'Conta da organização removida com sucesso.');
        }

        if ($user->certificates()->exists()) {
            throw new UserHasIssuedCertificatesException(
                "Usuário #{$user->id} possui certificados emitidos e não pode ser excluído."
            );
        }

        if ($user->createdInvitationLinks()->withoutGlobalScope('org')->exists()) {
            throw new UserHasCreatedInvitationLinksException(
                "Usuário #{$user->id} criou links de convite e não pode ser excluído."
            );
        }

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
