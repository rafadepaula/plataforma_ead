<?php

namespace App\Services;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * processes one CSV chunk (up to 50 rows, $O(1)$ RAM per
 * request) of Aluno enrollment data. Never receives the raw uploaded file:
 * `CsvImporter.js` reads/splits it client-side, this service only ever
 * sees an already-decoded array of rows plus the server-resolved
 * `org_id`/`course_id` for the current tenant context.
 *
 * If the row's e-mail already exists globally (the person is enrolled at a
 * different Organization), the existing `User` row is reused and a NEW
 * `credentials` account is provisioned for the importing Organization with
 * a random password (the student regains access through the portal's
 * forgot-password flow). A brand-new e-mail creates both the `User` and
 * the Organization account, then enrolls.
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
                    'name' => $name,
                    'email' => $email,
                    'cpf' => Cpf::digits($row['cpf'] ?? null),
                ]);
                $user->assignRole(RolesEnum::ALUNO->value);
                $created++;
            }

            // Reimportar uma pessoa cuja conta nesta org foi desativada
            // REATIVA a conta: o import existe para (re)dar acesso + matrícula.
            $credential = Credential::query()->firstOrCreate(
                ['user_id' => $user->id, 'org_id' => $orgId],
                ['password' => Hash::make(Str::random(32)), 'status' => 'active'],
            );

            if ($credential->status !== 'active') {
                $credential->forceFill(['status' => 'active'])->save();
            }

            if (! $user->courses()->withoutGlobalScopes()->where('course_id', $courseId)->exists()) {
                $user->courses()->withoutGlobalScopes()->attach($courseId, [
                    'enrolled_at' => now(),
                    'status' => 'active',
                    'progress_percentage' => 0,
                ]);
                $enrolled++;
            }
        }

        // `csv.import` is logged once per chunk request, since
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
