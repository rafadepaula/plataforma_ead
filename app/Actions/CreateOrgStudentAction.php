<?php

namespace App\Actions;

use App\Enums\Permissions\RolesEnum;
use App\Models\Course;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\StudentInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * creates a new Aluno for an Organization, optionally already enrolled in
 * one Course, in a single transaction — the shared engine behind both
 * creation surfaces (`EnrollmentController::storeStudent`, course-nested,
 * and `GestorStudentController::store`, directory-level with a course
 * picker).
 *
 * Multi-org is first-class: when the e-mail/CPF already belongs to an
 * existing person (validated by the `AlunoEmail`/`AlunoCpf` rules — same
 * person, never staff), this Action does NOT create a duplicate `users`
 * row. It links the person into the acting Organization (a `pending`
 * credential is provisioned if they don't hold one here), enrolls them
 * and get-or-creates the unique invitation — one `users` row, one
 * `credentials` row per Organization.
 *
 * The account is born `pending` with an unknowable random password: the
 * Aluno finalizes their own registration by redeeming the unique
 * `StudentInvitation` issued here (see `invitations-architecture`).
 */
class CreateOrgStudentAction
{
    /**
     * `$course` may be null on course-nested flows this Action does not
     * serve today, but is the normal path from the directory form.
     *
     * @return array{student: User, invitation: StudentInvitation, course: Course|null, existed: bool, enrolled: bool}
     */
    public function execute(Organization $org, string $name, string $email, string $cpf, ?Course $course, ?int $creatorId): array
    {
        return DB::transaction(function () use ($org, $name, $email, $cpf, $course, $creatorId): array {
            $student = User::query()->where('email', $email)->first();
            $existed = $student !== null;

            if ($existed) {
                // The person is already on the platform (another portal, or
                // this one). Never touch other orgs' credentials and never
                // a second `users` row — just make sure they hold the
                // Aluno role and a `pending` account HERE.
                if (! $student->hasRole(RolesEnum::ALUNO->value)) {
                    $student->assignRole(RolesEnum::ALUNO->value);
                }

                $credential = Credential::query()
                    ->forOrg($org->id)
                    ->where('user_id', $student->id)
                    ->first();

                if ($credential === null) {
                    $student->credentials()->create([
                        'org_id' => $org->id,
                        'password' => Hash::make(Str::random(32)),
                        'status' => 'pending',
                    ]);
                }
            } else {
                $student = User::create([
                    'name' => $name,
                    'email' => $email,
                    'cpf' => $cpf,
                ]);

                // pending until the Aluno redeems their unique invitation
                // and sets their own password — nobody (not even the
                // Gestor) knows this account's initial credential.
                $student->credentials()->create([
                    'org_id' => $org->id,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'pending',
                ]);
                $student->assignRole(RolesEnum::ALUNO->value);
            }

            $enrolled = false;

            if ($course !== null) {
                // read-then-branch upsert: the `UNIQUE(user_id, course_id)`
                // constraint makes a blind `attach()` blow up on re-enroll;
                // a previously `cancelled` row is reactivated instead.
                $alreadyEnrolled = $student->courses()
                    ->withoutGlobalScopes()
                    ->wherePivot('course_id', $course->id)
                    ->exists();

                if (! $alreadyEnrolled) {
                    $course->students()->attach($student->id, [
                        'enrolled_at' => now(),
                        'status' => 'active',
                    ]);
                    $enrolled = true;
                }
            }

            // get-or-create: a person linked from another portal may
            // already hold a live invitation — reuse it, never rotate.
            $invitation = StudentInvitation::query()
                ->where('user_id', $student->id)
                ->usable()
                ->first()
                ?? $student->studentInvitations()->create([
                    'org_id' => $org->id,
                    'token' => Str::random(64),
                    'created_by' => $creatorId,
                ]);

            return [
                'student' => $student,
                'invitation' => $invitation,
                'course' => $course,
                'existed' => $existed,
                'enrolled' => $enrolled,
            ];
        });
    }
}
