<?php

namespace App\Http\Requests;

use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates "Cadastrar novo aluno" from the Course enrollments panel:
 * creates the Aluno account (in the Course's org) and enrolls it into that
 * Course in one step. Mirrors `StoreUserRequest`'s field rules but is
 * Course-nested and Gestor-reachable: there is no `role` choice (the new
 * account is always an Aluno) and no `password` input — the CPF is both
 * identity and initial credential, hashed server-side in
 * `EnrollmentController::storeStudent()`. `org_id` is likewise resolved
 * from the route-bound Course, never trusted from request input.
 */
class StoreStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('course'));
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'cpf' => ['required', 'string', 'max:14', new Cpf, Rule::unique('users', 'cpf')],
        ];
    }
}
