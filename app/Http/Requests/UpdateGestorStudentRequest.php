<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates the Organizador's edits to one of their
 * own Organization's Alunos (`gestor.students.update`). Deliberately a
 * separate Form Request from `UpdateUserRequest` (see
 * `auth-orgs-conventions` — one Form Request per actor/surface, never a
 * mode flag): there is NO `role` field here — an Organizador manages
 * Alunos and can never promote/demote anyone — and `org_id` is absent
 * because it is immutable via this endpoint by design.
 */
class UpdateGestorStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateStudent', $target);
    }

    /**
     * @see Cpf::digits()
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('cpf')) {
            $this->merge(['cpf' => Cpf::digits($this->input('cpf'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'cpf' => ['nullable', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')->ignore($target->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // optional status toggle; when present and different from the
            // current value, `GestorStudentController::update()` records a
            // `user.status_changed` audit event.
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
