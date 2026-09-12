<?php

namespace App\Http\Requests;

use App\Rules\AlunoCpf;
use App\Rules\AlunoEmail;
use App\Rules\Cpf;
use Illuminate\Foundation\Http\FormRequest;

/**
 * validates "Cadastrar novo aluno" from the Course enrollments panel:
 * creates the Aluno account (in the Course's org) and enrolls it into that
 * Course in one step. Course-nested and Gestor-reachable: there is no
 * `role` choice (the account is always an Aluno) and no `password` input
 * — the credential is born `pending` and the Aluno finalizes it through
 * their unique invitation link. `org_id` is resolved from the route-bound
 * Course, never trusted from request input. An e-mail/CPF that already
 * exists is NOT an error by itself — multi-org people get LINKED, not
 * duplicated (see {@see AlunoEmail}/{@see AlunoCpf}).
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
            'email' => ['required', 'string', 'email', 'max:255', new AlunoEmail],
            'cpf' => ['required', 'string', 'max:14', new Cpf, new AlunoCpf],
        ];
    }
}
