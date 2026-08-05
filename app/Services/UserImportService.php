<?php

namespace App\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * RF05/RN09 — processes one CSV chunk (up to 50 rows, $O(1)$ RAM per
 * request) of Aluno enrollment data. Never receives the raw uploaded file:
 * `CsvImporter.js` reads/splits it client-side, this service only ever
 * sees an already-decoded array of rows plus the server-resolved
 * `org_id`/`course_id` for the current tenant context.
 *
 * RN09: if the row's e-mail already exists globally (the student is
 * enrolled at a different Organization), the existing `User` row is
 * reused as-is — its `password` and `org_id` are never touched — and only
 * a new `course_user` enrollment is inserted for the current Org's
 * target course. A brand-new e-mail creates the `User` bound to the
 * current `org_id` and enrolls it.
 */
class UserImportService
{
    /**
     * @param  array<int, array{name?: string|null, email?: string|null, cpf?: string|null}>  $rows
     * @return array{created: int, enrolled: int, skipped: list<array{row: int, reason: string}>}
     */
    public function importChunk(array $rows, int $courseId, int $orgId, ?string $fileName = null): array
    {
        $created = 0;
        $enrolled = 0;
        $skipped = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));

            if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped[] = ['row' => $index, 'reason' => 'Nome ou e-mail ausente/inválido.'];

                continue;
            }

            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'org_id' => $orgId,
                    'name' => $name,
                    'email' => $email,
                    'cpf' => $row['cpf'] ?? null,
                    'password' => Hash::make(Str::random(32)),
                    'status' => 'active',
                ]);
                $user->assignRole(RolesEnum::ALUNO->value);
                $created++;
            }

            if (! $user->courses()->where('course_id', $courseId)->exists()) {
                $user->courses()->attach($courseId, [
                    'enrolled_at' => now(),
                    'status' => 'active',
                    'progress_percentage' => 0,
                ]);
                $enrolled++;
            }
        }

        // SPEC-15 §3 — `csv.import` is logged once per chunk request, since
        // this service has no concept of a logical "import session" spanning
        // the multiple 50-row chunks `CsvImporter.js` sends per upload (see
        // `audit-logs-architecture`'s open question). Audit failures never
        // block the import itself.
        try {
            AuditService::log(
                event: 'csv.import',
                orgId: $orgId,
                userId: Auth::id(),
                payload: [
                    'total_processed' => count($rows),
                    'success_count' => $created + $enrolled,
                    'error_count' => count($skipped),
                    'file_name' => $fileName,
                ],
            );
        } catch (Throwable $e) {
            report($e);
        }

        return ['created' => $created, 'enrolled' => $enrolled, 'skipped' => $skipped];
    }
}
