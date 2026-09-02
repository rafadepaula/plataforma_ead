<?php

namespace App\Http\Controllers;

use App\Enums\Permissions\RolesEnum;
use App\Exceptions\UserHasCreatedInvitationLinksException;
use App\Exceptions\UserHasIssuedCertificatesException;
use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\UpdateGestorStudentRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 *  the Organizador's exclusive Aluno directory at
 * `gestor/students` (`gestor.students.*`, `role:gestor`). Lists ONLY the
 * Aluno accounts enrolled in the acting Gestor's own Organization's
 * Courses, and lets them view and manage exactly those Alunos — edit
 * profile/status and remove — never another staff account and never a
 * foreign tenant (see `UserPolicy::viewAnyStudents`/`updateStudent`/
 * `deleteStudent`, which are the enforcement point).
 *
 * Deliberately a separate controller from the Admin-only
 * `UserController`/`UserAdminController` stack (see `auth-orgs-conventions`):
 * there is no create/role-change surface here. New Alunos enter the
 * Organization through invitation links, the shared CSV import
 * (`users.import.*`, `role:admin|gestor`) or per-Course manual enrollment,
 * and staff accounts are an Admin matter on `users.*`.
 */
class GestorStudentController extends Controller
{
    use ResolvesOrgContext;

    public function index(Request $request): View
    {
        Gate::authorize('viewAnyStudents', User::class);

        $orgId = $this->resolveOrgId($request);

        $students = User::query()
            ->where('org_id', $orgId)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RolesEnum::ALUNO->value))
            //  "alunos matriculados nos cursos da própria
            // Organização": a live `course_user` row for an own-org Course.
            // A `cancelled` enrollment is revoked history, not an
            // enrollment, so it drops the Aluno back out of this listing.
            ->whereHas('courses', fn (Builder $query) => $query
                ->where('courses.org_id', $orgId)
                ->where('course_user.status', '!=', 'cancelled'))
            ->with(['courses' => fn (BelongsToMany $query) => $query
                ->where('courses.org_id', $orgId)
                ->where('course_user.status', '!=', 'cancelled')])
            ->orderBy('name')
            ->paginate(20);

        return view('gestor.students.index', compact('students'));
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('updateStudent', $user);

        $user->load('roles');

        return view('gestor.students.edit', compact('user'));
    }

    public function update(UpdateGestorStudentRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // `role` is not part of this screen's validation surface
        // (`UpdateGestorStudentRequest` never accepts it): an Organizador
        // manages Alunos, so the target always keeps its Aluno role.

        $oldStatus = $user->getOriginal('status');

        $user->update($data);

        // `user.status_changed` is a critical-action event
        // distinct from `AuditableTrait`'s generic `user.updated` mutation
        // row; it is only recorded when `status` actually changed. Mirrors
        // `UserController::update()`'s audit block.
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

        return redirect()->route('gestor.students.index')->with('success', 'Aluno atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deleteStudent', $user);

        //  the same `ON DELETE RESTRICT` pre-flight
        // guards as the global Admin screen: `users` has no `deleted_at`,
        // so a hard delete of an Aluno with issued certificates or created
        // invitation links would otherwise crash with a raw 500
        // QueryException.
        if ($user->certificates()->exists()) {
            throw new UserHasIssuedCertificatesException(
                "Aluno #{$user->id} possui certificados emitidos e não pode ser excluído."
            );
        }

        if ($user->createdInvitationLinks()->withoutGlobalScope('org')->exists()) {
            throw new UserHasCreatedInvitationLinksException(
                "Aluno #{$user->id} criou links de convite e não pode ser excluído."
            );
        }

        $user->delete();

        return redirect()->route('gestor.students.index')->with('success', 'Aluno removido com sucesso.');
    }

    /**
     * @see ResolvesOrgContext::resolveOrgId()
     */
    protected function orgContextAction(): string
    {
        return 'gerenciar os alunos da organização';
    }
}
