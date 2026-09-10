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
 * {@see UpdateUserRequest} (the operational `users.update` counterpart),
 * `role` allows all 4 {@see RolesEnum} values. Org membership and
 * account status are NOT editable here anymore: under host-based tenancy
 * each Organization account (`credentials` row) carries its own status,
 * and memberships are managed on each portal.
 */
class UpdateUserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('updateGlobal', $target);
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
            'role' => ['required', Rule::in([
                RolesEnum::ADMIN->value,
                RolesEnum::GESTOR->value,
                RolesEnum::ALUNO->value,
                RolesEnum::PROFESSOR->value,
            ])],
            // When set, applies to ALL of the person's per-org accounts —
            // this is the global admin surface.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }
}
