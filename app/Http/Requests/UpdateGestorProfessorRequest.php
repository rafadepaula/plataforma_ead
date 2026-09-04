<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 *  validates the Gestor's edits to one of their own
 * Organization's Professors (`gestor.professors.update`). Mirrors
 * {@see UpdateGestorStudentRequest} one role over: there is NO `role`
 * field (a Gestor manages Docentes and can never promote/demote anyone)
 * and NO `org_id` — both immutable via this endpoint by design.
 */
class UpdateGestorProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target);
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
            // current value, `GestorProfessorController::update()` records
            // a `user.status_changed` audit event.
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
