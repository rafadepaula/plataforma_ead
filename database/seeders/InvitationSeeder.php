<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\InvitationLink;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvitationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * SPEC-16 §2.2 / SPEC-06 — Seeds active and expired InvitationLink records for test
     * organizations using explicit org_id and withoutEvents() event suppression.
     */
    public function run(): void
    {
        InvitationLink::withoutEvents(function (): void {
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
                $creator = User::query()->where('org_id', $org->id)->first()
                    ?? User::query()->first()
                    ?? User::firstOrCreate(
                        ['email' => "gestor.{$org->slug}@example.com"],
                        [
                            'org_id' => $org->id,
                            'name' => "Gestor {$org->name}",
                            'password' => bcrypt('password'),
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]
                    );

                $course = Course::withoutGlobalScopes()->where('org_id', $org->id)->first()
                    ?? Course::firstOrCreate(
                        ['org_id' => $org->id, 'title' => "Curso Base {$org->name}"],
                        [
                            'description' => 'Curso de demonstração para convites inteligentes',
                            'is_published' => true,
                        ]
                    );

                // Active Invitation Link
                InvitationLink::firstOrCreate(
                    ['token' => hash('sha256', "invitation-token-active-{$org->id}")],
                    [
                        'org_id' => $org->id,
                        'course_id' => $course->id,
                        'max_uses' => 50,
                        'current_uses' => 5,
                        'expires_at' => now()->addDays(30),
                        'revoked_at' => null,
                        'created_by' => $creator->id,
                    ]
                );

                // Expired Invitation Link
                InvitationLink::firstOrCreate(
                    ['token' => hash('sha256', "invitation-token-expired-{$org->id}")],
                    [
                        'org_id' => $org->id,
                        'course_id' => $course->id,
                        'max_uses' => 10,
                        'current_uses' => 10,
                        'expires_at' => now()->subDays(5),
                        'revoked_at' => null,
                        'created_by' => $creator->id,
                    ]
                );
            }
        });
    }
}
