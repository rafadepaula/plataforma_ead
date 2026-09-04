<?php

namespace App\Http\Requests;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 *  validates the attach action of the per-course
 * Professor assignment panel (`courses.professors.store`). Beyond the
 * `exists` guard, the target must be a `professor` of the SAME
 * Organization as the Course — the whole tenancy contract of the
 * assignment (same-org-but-unassigned is still a 403 for the Professor,
 * so a cross-org row must never be creatable in the first place).
 * Duplicates are additionally impossible: the pivot's
 * `UNIQUE(course_id, user_id)` backs this up at the base level.
 */
class AttachCourseProfessorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course instanceof Course && $this->user()?->can('update', $course);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail) use ($course): void {
                    $professor = User::query()->find($value);

                    if ($professor === null) {
                        return; // `exists` already fails this request.
                    }

                    if (! $professor->hasRole(RolesEnum::PROFESSOR->value)) {
                        $fail('O usuário selecionado não é um Professor.');

                        return;
                    }

                    if ((int) $professor->org_id !== (int) $course->org_id) {
                        $fail('O Professor deve pertencer à mesma Organização do curso.');
                    }
                },
            ],
        ];
    }
}
