<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrgStudentAction;
use App\Enums\Permissions\RolesEnum;
use App\Events\EnrollmentConfirmed;
use App\Exceptions\UserHasIssuedCertificatesException;
use App\Http\Controllers\Concerns\ResolvesOrgContext;
use App\Http\Requests\StoreGestorStudentRequest;
use App\Http\Requests\UpdateGestorStudentRequest;
use App\Models\Course;
use App\Models\User;
use App\Rules\Cpf;
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
 * there is no role-change surface here. New Alunos enter the Organization
 * through this controller's own one-step create form (`create`/`store`,
 * with the course picker and the unique invitation link), the shared CSV
 * import (`users.import.*`, `role:admin|gestor`), the course-nested
 * enrollment panel or per-Course manual enrollment — and staff accounts
 * are an Admin matter on `users.*`.
 */
class GestorStudentController extends Controller
{
    use ResolvesOrgContext;

    public function __construct(private readonly CreateOrgStudentAction $createOrgStudentAction) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAnyStudents', User::class);

        $orgId = $this->resolveOrgId($request);

        $searchInput = $request->input('search');
        $search = is_string($searchInput) ? trim($searchInput) : '';
        $cpfDigits = Cpf::digits($search);

        $students = User::query()
            ->whereHas('credentials', fn ($query) => $query->where('org_id', $orgId))
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
            // Same name/e-mail/CPF match as the enrollments panel's
            // autocomplete feed: the CPF arm only fires when the typed term
            // actually carries digits, so a plain name search never
            // collides with the digits-only `cpf` column.
            ->when($search !== '', fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereLike('name', "%{$search}%")
                    ->orWhereLike('email', "%{$search}%")
                    ->when($cpfDigits !== null, fn (Builder $query): Builder => $query->orWhereLike('cpf', "%{$cpfDigits}%"))))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('gestor.students.index', ['students' => $students, 'search' => $search]);
    }

    /**
     * the directory-level "Cadastrar aluno" screen: same identity fields
     * as the course-nested form, but with the Course picker right on the
     * form — the Gestor creates AND enrolls without leaving the
     * directory. `course_id` is REQUIRED because this listing only shows
     * enrolled Alunos; creating without one would make the person
     * invisible in the Gestor's own UI.
     */
    public function create(): View
    {
        Gate::authorize('viewAnyStudents', User::class);

        $courses = Course::query()
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('gestor.students.create', ['courses' => $courses]);
    }

    /**
     * creates the Aluno (pending credential + unique invitation via
     * {@see CreateOrgStudentAction}) already enrolled in the picked
     * Course, then hands the invitation link back through the
     * `invitation_url` flash — the same handoff the enrollment panel
     * uses. `EnrollmentConfirmed` dispatches only after the Action's
     * transaction commits.
     */
    public function store(StoreGestorStudentRequest $request): RedirectResponse
    {
        Gate::authorize('viewAnyStudents', User::class);

        $orgId = $this->resolveOrgId($request);
        $data = $request->validated();

        $course = Course::query()->findOrFail($data['course_id']);

        $result = $this->createOrgStudentAction->execute(
            $course->organization,
            $data['name'],
            $data['email'],
            $data['cpf'],
            $course,
            auth()->id(),
        );

        if ($result['enrolled']) {
            EnrollmentConfirmed::dispatch($course, $result['student']);
        }

        return redirect()->route('gestor.students.index')
            ->with('success', $this->creationMessage($result['existed'], $result['enrolled']))
            ->with('invitation_url', url('/convite/'.$result['invitation']->token));
    }

    /**
     * Success copy reflects what actually happened — linking an existing
     * multi-org person reads differently from a brand-new registration.
     */
    private function creationMessage(bool $existed, bool $enrolled): string
    {
        return match (true) {
            ! $existed => 'Aluno cadastrado e matriculado com sucesso. Envie o link de convite para ele criar a senha.',
            $enrolled => 'Aluno já existente na plataforma: conta vinculada e matriculada com sucesso.',
            default => 'Aluno já pertence à sua organização. O link de convite serve para ele acessar ou redefinir a senha.',
        };
    }

    public function edit(Request $request, User $user): View
    {
        Gate::authorize('updateStudent', $user);

        $user->load('roles');

        return view('gestor.students.edit', compact('user'));
    }

    public function update(UpdateGestorStudentRequest $request, User $user): RedirectResponse
    {
        $orgId = $this->resolveOrgId($request);
        $data = $request->validated();

        $credential = $user->credentials()->forOrg($orgId)->first();

        if (! $credential) {
            abort(404);
        }

        // `role` is not part of this screen's validation surface
        // (`UpdateGestorStudentRequest` never accepts it): an Organizador
        // manages Alunos, so the target always keeps its Aluno role.

        $oldStatus = $credential->getOriginal('status');

        $user->update(collect($data)->only(['name', 'email', 'cpf'])->all());

        if (! empty($data['password'])) {
            $credential->forceFill(['password' => Hash::make($data['password'])])->save();
            // senha imposta invalida o "lembrar-me" desta conta
            $credential->rotateRememberToken();
        }

        // `user.status_changed` is a critical-action event
        // distinct from `AuditableTrait`'s generic `user.updated` mutation
        // row; it is only recorded when `status` actually changed. Mirrors
        // `UserController::update()`'s audit block.
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

        return redirect()->route('gestor.students.index')->with('success', 'Aluno atualizado com sucesso.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('deleteStudent', $user);

        $orgId = $this->resolveOrgId($request);
        $credential = $user->credentials()->forOrg($orgId)->first();

        if (! $credential) {
            abort(404);
        }

        // Removing from THIS portal only — the person row is hard-deleted
        // only when this was their last account, with the same
        // `ON DELETE RESTRICT` pre-flight guards as the global Admin
        // screen (raw 500 otherwise: `users` has no `deleted_at`).
        if ($user->credentials()->count() > 1) {
            $credential->delete();

            return redirect()->route('gestor.students.index')->with('success', 'Conta da organização removida com sucesso.');
        }

        if ($user->certificates()->exists()) {
            throw new UserHasIssuedCertificatesException(
                "Aluno #{$user->id} possui certificados emitidos e não pode ser excluído."
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
