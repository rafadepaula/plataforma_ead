<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * RF04 — validates updates to an existing Aluno/Gestor. `org_id` is
 * intentionally absent: `UserController::update()` never mass-assigns it
 * from request input, it is immutable via this endpoint by design.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target);
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
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('users', 'cpf')->ignore($target->id)],
            'role' => ['required', Rule::in([RolesEnum::ALUNO->value, RolesEnum::GESTOR->value])],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // SPEC-15 §3 — optional status toggle; when present and
            // different from the current value, `UserController::update()`
            // records a `user.status_changed` audit event (RF32).
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
