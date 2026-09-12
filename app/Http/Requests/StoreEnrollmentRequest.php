<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * validates manual enrollment of an existing `User` into a
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
                //  a Gestor may only manually enroll a User who
                // already holds an account (credential) in their own org:
                // no cross-org "guess the ID" force enrollment. `pending`
                // accounts (awaiting the Aluno's unique-invite
                // finalization) count — only a Gestor-imposed `inactive`
                // deactivation is unenrollable.
                function (string $attribute, mixed $value, \Closure $fail) use ($course): void {
                    $holdsAccount = Credential::query()
                        ->where('user_id', $value)
                        ->where('org_id', $course?->org_id)
                        ->whereIn('status', ['active', 'pending'])
                        ->exists();

                    if (! $holdsAccount) {
                        $fail('Este usuário não pertence à sua organização.');
                    }
                },
                // The target must actually hold the `aluno` role — never a
                // `gestor`/`admin` account, the same staff-exclusion rule
                // the invitation redemption enforces.
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
