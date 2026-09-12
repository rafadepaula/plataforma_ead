<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\AlunoCpf;
use App\Rules\AlunoEmail;
use App\Rules\Cpf;
use App\Services\OrgContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates the directory-level "Cadastrar aluno" form
 * (`gestor.students.store`). Same identity surface as the course-nested
 * `StoreStudentEnrollmentRequest` (there is NO password field: the
 * account is born `pending` and the Aluno finalizes it through their
 * unique invitation link), plus a REQUIRED `course_id` — the directory
 * lists only enrolled Alunos, so creating without a course would make
 * the person invisible in the Gestor's own UI. The course must belong to
 * the acting Organization: `org_id` never comes from the request, it is
 * resolved server-side. An e-mail/CPF that already exists is NOT an
 * error by itself — multi-org people get LINKED, not duplicated (see
 * {@see AlunoEmail}/{@see AlunoCpf}).
 */
class StoreGestorStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAnyStudents', User::class) ?? false;
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
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', new AlunoEmail],
            'cpf' => ['required', 'string', 'max:14', new Cpf, new AlunoCpf],
            'course_id' => [
                'required',
                Rule::exists('courses', 'id')->where('org_id', OrgContext::current()->orgId()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'course_id.required' => 'Selecione o curso em que o aluno será matriculado.',
            'course_id.exists' => 'O curso selecionado não pertence à sua organização.',
        ];
    }
}
