<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPEC-12 — streams a CSV export for the "Central de Exportação" via
 * `response()->streamDownload()` + `fputcsv()`, reading rows in
 * `chunk()`-ed batches so peak memory stays O(1) regardless of dataset
 * size (SPEC-00 §1.2's 128M shared-hosting constraint) — never buffer the
 * full result set into an array/Collection first.
 *
 * `certificates`/`course_user` are cascade-inherited tenancy (no
 * `OrgScope` of their own — see `dashboard-architecture`), so every query
 * here joins through `courses.org_id` and takes an explicit,
 * already-resolved `$orgId` (`null` meaning "no filter", i.e. an Admin
 * with no active Impersonate Org context) rather than reading
 * `Auth::user()`/`session('active_org_id')` itself. Resolving that org id
 * from the request (and rejecting a Gestor's spoofed `?org_id=`) is the
 * caller's (`ReportExportController`'s) responsibility.
 */
class CsvStreamExportService
{
    private const CHUNK_SIZE = 500;

    /**
     * @var array<string, list<string>>
     */
    private const HEADERS = [
        'enrollments' => ['Aluno', 'E-mail', 'Curso', 'Status', 'Progresso (%)', 'Matriculado em'],
        'certificates' => ['Aluno', 'Curso', 'Hash de Validação', 'Emitido em', 'Revogado em'],
    ];

    /**
     * @throws InvalidArgumentException when `$type` is not a supported report type.
     */
    public function stream(string $type, ?int $orgId): StreamedResponse
    {
        if (! array_key_exists($type, self::HEADERS)) {
            throw new InvalidArgumentException("Tipo de relatório desconhecido: \"{$type}\".");
        }

        $filename = sprintf('%s-%s.csv', $type, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($type, $orgId): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, self::HEADERS[$type]);

            match ($type) {
                'enrollments' => $this->writeEnrollments($handle, $orgId),
                'certificates' => $this->writeCertificates($handle, $orgId),
            };

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  resource  $handle
     */
    private function writeEnrollments($handle, ?int $orgId): void
    {
        DB::table('course_user')
            ->join('users', 'users.id', '=', 'course_user.user_id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->orderBy('course_user.id')
            ->select([
                'users.name as student_name',
                'users.email as student_email',
                'courses.title as course_name',
                'course_user.status',
                'course_user.progress_percentage',
                'course_user.enrolled_at',
            ])
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->student_name,
                        $row->student_email,
                        $row->course_name,
                        $row->status,
                        $row->progress_percentage,
                        $row->enrolled_at,
                    ]);
                }
            });
    }

    /**
     * @param  resource  $handle
     */
    private function writeCertificates($handle, ?int $orgId): void
    {
        DB::table('certificates')
            ->join('users', 'users.id', '=', 'certificates.user_id')
            ->join('courses', 'courses.id', '=', 'certificates.course_id')
            ->when($orgId !== null, fn ($query) => $query->where('courses.org_id', $orgId))
            ->orderBy('certificates.id')
            ->select([
                'users.name as student_name',
                'courses.title as course_name',
                'certificates.validation_hash',
                'certificates.issued_at',
                'certificates.revoked_at',
            ])
            ->chunk(self::CHUNK_SIZE, function ($rows) use ($handle): void {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->student_name,
                        $row->course_name,
                        $row->validation_hash,
                        $row->issued_at,
                        $row->revoked_at,
                    ]);
                }
            });
    }
}
