<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UserHasCreatedInvitationLinksException;
use App\Exceptions\UserHasIssuedCertificatesException;
use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\StoreGestorProfessorRequest;
use App\Http\Requests\UpdateGestorProfessorRequest;
use App\Models\User;
use App\Rules\Cpf;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * the Gestor's exclusive Professor directory at
 * `gestor/professors` (`gestor.professors.*`, `role:gestor`). The docente
 * counterpart of {@see GestorStudentController}: lists ONLY the
 * `professor` accounts of the acting Gestor's own Organization and lets
 * them create/edit/delete exactly those — never another staff account and
 * never a foreign tenant. The `org_id` of created professors is resolved
 * server-side via `ResolvesOrgContext`, never trusted from the request,
 * and there is no role/org change surface (`UpdateGestorProfessorRequest`
 * never accepts either field).
 *
 * Authorization reuses `UserPolicy`'s existing `viewAny`/`update`/`delete`
 * (`sharesOrgContext()` already isolates between Organizations for a
 * Gestor carrying an `org_id`) plus an explicit target-is-Professor check
 * in this controller, since `sharesOrgContext()` alone would also admit a
 * same-org Gestor/Aluno as the target of a guessed URL.
 */
class GestorProfessorController extends Controller
{
    use ResolvesOrgContext;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $orgId = $this->resolveOrgId($request);

        $searchInput = $request->input('search');
        $search = is_string($searchInput) ? trim($searchInput) : '';
        $cpfDigits = Cpf::digits($search);

        $professors = User::query()
            ->where('org_id', $orgId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RolesEnum::PROFESSOR->value))
            ->withCount('taughtCourses')
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->when($cpfDigits !== null, fn (Builder $query): Builder => $query->orWhereLike('cpf', "%{$cpfDigits}%"))))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('gestor.professors.index', ['professors' => $professors, 'search' => $search]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', User::class);

        $this->resolveOrgId($request);

        return view('gestor.professors.create');
    }

    public function store(StoreGestorProfessorRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $data = $request->validated();
        $data['org_id'] = $this->resolveOrgId($request);
        $data['password'] = Hash::make($data['password']);
        $data['status'] = 'active';

        $professor = User::create($data);
        $professor->assignRole(RolesEnum::PROFESSOR->value);

        return redirect()->route('gestor.professors.index')
            ->with('success', 'Professor cadastrado com sucesso.');
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('update', $user);
        $this->abortUnlessProfessor($user);

        $user->load('roles');

        return view('gestor.professors.edit', compact('user'));
    }

    public function update(UpdateGestorProfessorRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->abortUnlessProfessor($user);

        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // `role` and `org_id` are not part of this screen's validation
        // surface (`UpdateGestorProfessorRequest` never accepts them): a
        // Gestor manages the Docentes of their own Organization and can
        // never change what they are or where they belong.

        $oldStatus = $user->getOriginal('status');

        $user->update($data);

        // `user.status_changed` mirrors `GestorStudentController::update()`'s
        // critical-action audit row.
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

        return redirect()->route('gestor.professors.index')
            ->with('success', 'Professor atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $this->abortUnlessProfessor($user);

        // Same `ON DELETE RESTRICT` pre-flight guards as the
        // global Admin screen — defensive today (a Professor never owns
        // certificates nor creates invitation links), but cheap: without
        // them a hard delete would crash with a raw 500 QueryException.
        if ($user->certificates()->exists()) {
            throw new UserHasIssuedCertificatesException(
                "Professor #{$user->id} possui certificados emitidos e não pode ser excluído."
            );
        }

        if ($user->createdInvitationLinks()->withoutGlobalScope('org')->exists()) {
            throw new UserHasCreatedInvitationLinksException(
                "Professor #{$user->id} criou links de convite e não pode ser excluído."
            );
        }

        $user->delete();

        return redirect()->route('gestor.professors.index')
            ->with('success', 'Professor removido com sucesso.');
    }

    /**
     * `UserPolicy::sharesOrgContext()` (driving `update`/`delete`) only
     * compares tenants — a guessed `gestor/professors/{gestor}/edit` URL
     * would pass it. The screen's boundary is "same-org PROFESSOR only",
     * so the target's role is re-checked here.
     */
    protected function abortUnlessProfessor(User $user): void
    {
        abort_unless($user->hasRole(RolesEnum::PROFESSOR->value), 404);
    }

    /**
     * @see ResolvesOrgContext::resolveOrgId()
     */
    protected function orgContextAction(): string
    {
        return 'gerenciar os professores da organização';
    }
}
