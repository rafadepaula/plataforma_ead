<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SPEC-06 RF21 — validates manual enrollment of an existing `User` into a
 * Course via the Gestor panel. `course_id` is resolved from the
 * route-bound `{course}` segment, never trusted from request input.
 */
class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('course'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'user_id' => [
                'required',
                'integer',
                // RF21 — a Gestor may only manually enroll a User who
                // already belongs to their own org, closing the same gap
                // `ProcessSmartInvitationAction` guards for the
                // self-service flow: no cross-org "guess the ID" force
                // enrollment.
                Rule::exists('users', 'id')->where('org_id', $course?->org_id),
                // The target must actually hold the `aluno` role — never a
                // `gestor`/`admin` account, mirroring
                // `ProcessSmartInvitationAction`'s rejection of staff
                // emails from the self-service invitation flow.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $user = User::find($value);

                    if ($user && ! $user->hasRole(RolesEnum::ALUNO->value)) {
                        $fail('Este usuário não pode ser matriculado como aluno.');
                    }
                },
                Rule::unique('course_user', 'user_id')
                    ->where('course_id', $course?->id)
                    ->where('status', 'active'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'Este usuário não pertence à sua organização.',
            'user_id.unique' => 'Este usuário já está ativamente matriculado neste curso.',
        ];
    }
}
