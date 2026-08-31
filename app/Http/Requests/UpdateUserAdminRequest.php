<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates the full-profile edit on the global Admin
 * user-management screen (`admin.users.update`). Unlike
 * {@see UpdateUserRequest} (the operational `users.update` counterpart):
 *  - `role` allows all 3 {@see RolesEnum} values, not just aluno/gestor;
 *  - `org_id` is editable (this is the cross-org "full profile" editor),
 *    required whenever `role` is not `admin` (an admin has no
 *    Organization), forbidden/nullable when it is.
 */
class UpdateUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateGlobal', $target);
    }

    /**
     * Forces `org_id` to `null` whenever the submitted `role` is `admin`,
     * regardless of what the (possibly stale, pre-filled) `org_id` field
     * carried in the payload. This keeps the platform-wide invariant that
     * Admins are org-less even when the caller only changes the role
     * select without clearing the Organização field. Also normalises the CPF
     * to digits only ({@see Cpf::digits()}).
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('role') === RolesEnum::ADMIN->value) {
            $this->merge(['org_id' => null]);
        }

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
            'role' => ['required', Rule::in([
                RolesEnum::ADMIN->value,
                RolesEnum::GESTOR->value,
                RolesEnum::ALUNO->value,
            ])],
            'org_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== RolesEnum::ADMIN->value),
                Rule::prohibitedIf(fn () => $this->input('role') === RolesEnum::ADMIN->value),
                'nullable',
                'exists:organizations,id',
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // optional status toggle; when present and
            // different from the current value, `UserAdminController`
            // records a `user.status_changed` audit event .
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
