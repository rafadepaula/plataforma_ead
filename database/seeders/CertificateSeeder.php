<?php

namespace Database\Seeders;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * SPEC-16 §2.2 / SPEC-09 — Seeds issued certificates (valid active and revoked)
     * with fixed SHA-256 validation hashes for verification testing.
     */
    public function run(): void
    {
        Certificate::withoutEvents(function (): void {
            $organizations = Organization::query()->get();

            if ($organizations->isEmpty()) {
                $org = Organization::firstOrCreate(
                    ['slug' => 'acme-cursos'],
                    [
                        'name' => 'Acme Cursos',
                        'cnpj' => '12.345.678/0001-90',
                        'status' => 'active',
                    ]
                );
                $organizations = collect([$org]);
            }

            foreach ($organizations as $org) {
                $course = Course::withoutGlobalScopes()->where('org_id', $org->id)->first()
                    ?? Course::firstOrCreate(
                        ['org_id' => $org->id, 'title' => "Curso com Certificado {$org->name}"],
                        [
                            'description' => 'Curso de teste para emissão de certificados',
                            'is_published' => true,
                        ]
                    );

                $student1 = User::query()->where('org_id', $org->id)->where('email', 'like', 'aluno1%')->first()
                    ?? User::query()->where('org_id', $org->id)->first()
                    ?? User::firstOrCreate(
                        ['email' => "aluno.cert1.{$org->slug}@example.com"],
                        [
                            'org_id' => $org->id,
                            'name' => "Aluno Certificado 1 {$org->name}",
                            'password' => bcrypt('password'),
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]
                    );

                $student2 = User::query()->where('org_id', $org->id)->where('email', 'like', 'aluno2%')->first()
                    ?? User::firstOrCreate(
                        ['email' => "aluno.cert2.{$org->slug}@example.com"],
                        [
                            'org_id' => $org->id,
                            'name' => "Aluno Certificado 2 {$org->name}",
                            'password' => bcrypt('password'),
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]
                    );

                $gestor = User::query()->where('org_id', $org->id)->where('email', 'like', 'gestor%')->first()
                    ?? User::firstOrCreate(
                        ['email' => "gestor.cert.{$org->slug}@example.com"],
                        [
                            'org_id' => $org->id,
                            'name' => "Gestor Certificadores {$org->name}",
                            'password' => bcrypt('password'),
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]
                    );

                // Valid Active Certificate (SHA-256)
                Certificate::firstOrCreate(
                    [
                        'user_id' => $student1->id,
                        'course_id' => $course->id,
                    ],
                    [
                        'validation_hash' => hash('sha256', "valid-certificate-hash-{$org->id}-{$student1->id}-{$course->id}"),
                        'issued_at' => now()->subDays(10),
                        'revoked_at' => null,
                        'revoked_by' => null,
                        'revoke_reason' => null,
                    ]
                );

                // Revoked Certificate (SHA-256)
                Certificate::firstOrCreate(
                    [
                        'user_id' => $student2->id,
                        'course_id' => $course->id,
                    ],
                    [
                        'validation_hash' => hash('sha256', "revoked-certificate-hash-{$org->id}-{$student2->id}-{$course->id}"),
                        'issued_at' => now()->subDays(20),
                        'revoked_at' => now()->subDays(2),
                        'revoked_by' => $gestor->id,
                        'revoke_reason' => 'Emissão revogada por descumprimento das regras de integridade do curso.',
                    ]
                );
            }
        });
    }
}
