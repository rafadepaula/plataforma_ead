<?php

namespace App\Rules;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The Gestor-driven create-a-student forms accept a person who ALREADY
 * exists on the platform — multi-org is a first-class scenario (one
 * `users` row, one `credentials` row per Organization). So an existing
 * e-mail is NOT an error by itself: it is an error only when the CPF
 * typed does not match the existing account (someone else's e-mail) or
 * when the account belongs to staff, who can never be enrolled as an
 * Aluno. When the identity matches, `CreateOrgStudentAction` links the
 * existing person into the acting Organization instead of creating a
 * duplicate.
 */
class AlunoEmail implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = User::query()->where('email', $value)->first();

        if ($existing === null) {
            return;
        }

        if ($existing->cpf !== null && $existing->cpf !== Cpf::digits((string) request()->input('cpf'))) {
            $fail('Já existe uma conta com este e-mail, mas o CPF informado não corresponde a ela. Confira os dados com o aluno ou, se ele já estuda com vocês, matricule a conta existente pelo painel "Matrículas" do curso.');

            return;
        }

        if ($existing->hasAnyRole([RolesEnum::GESTOR->value, RolesEnum::ADMIN->value])) {
            $fail('Este e-mail pertence a uma conta da equipe (gestão) e não pode ser cadastrada como aluno.');
        }
    }
}
