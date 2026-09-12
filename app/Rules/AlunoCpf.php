<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Mirror of {@see AlunoEmail} for the CPF side: a CPF that already
 * exists is fine when it belongs to the same person being typed (same
 * e-mail — the multi-org link case), but not when it points at another
 * e-mail, which would silently merge two different people.
 */
class AlunoCpf implements ValidationRule
{
    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $owner = User::query()->where('cpf', $value)->first();

        if ($owner !== null && strcasecmp($owner->email, (string) request()->input('email')) !== 0) {
            $fail('Este CPF já está cadastrado na plataforma com o e-mail '.$owner->email.'. Confira com o aluno qual e-mail ele utiliza.');
        }
    }
}
