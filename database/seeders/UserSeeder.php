<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acme = Organization::where('slug', 'acme-cursos')->first();
        $tech = Organization::where('slug', 'tech-academy')->first();

        if (! $acme || ! $tech) {
            $this->call(OrganizationSeeder::class);
            $acme = Organization::where('slug', 'acme-cursos')->first();
            $tech = Organization::where('slug', 'tech-academy')->first();
        }

        User::withoutEvents(function () use ($acme, $tech): void {
            // Seed Gestores
            $gestorAcme = User::firstOrCreate(
                ['email' => 'gestor.acme@plataforma.com'],
                [
                    'name' => 'Gestor Acme',
                    'password' => Hash::make('password'),
                    'org_id' => $acme?->id,
                    'cpf' => '111.111.111-11',
                    'status' => 'active',
                ]
            );
            if (! $gestorAcme->email_verified_at) {
                $gestorAcme->forceFill(['email_verified_at' => now()])->save();
            }
            if (! $gestorAcme->hasRole(RolesEnum::GESTOR->value)) {
                $gestorAcme->assignRole(RolesEnum::GESTOR->value);
            }

            $gestorTech = User::firstOrCreate(
                ['email' => 'gestor.tech@plataforma.com'],
                [
                    'name' => 'Gestor Tech',
                    'password' => Hash::make('password'),
                    'org_id' => $tech?->id,
                    'cpf' => '222.222.222-22',
                    'status' => 'active',
                ]
            );
            if (! $gestorTech->email_verified_at) {
                $gestorTech->forceFill(['email_verified_at' => now()])->save();
            }
            if (! $gestorTech->hasRole(RolesEnum::GESTOR->value)) {
                $gestorTech->assignRole(RolesEnum::GESTOR->value);
            }

            // Seed Alunos
            $alunosData = [
                [
                    'email' => 'aluno.alpha@plataforma.com',
                    'name' => 'Aluno Alpha',
                    'cpf' => '333.333.333-33',
                    'org_id' => $acme?->id,
                ],
                [
                    'email' => 'aluno.beta@plataforma.com',
                    'name' => 'Aluno Beta',
                    'cpf' => '444.444.444-44',
                    'org_id' => $acme?->id,
                ],
                [
                    'email' => 'aluno.gamma@plataforma.com',
                    'name' => 'Aluno Gamma',
                    'cpf' => '555.555.555-55',
                    'org_id' => $tech?->id,
                ],
                [
                    'email' => 'aluno.delta@plataforma.com',
                    'name' => 'Aluno Delta',
                    'cpf' => '666.666.666-66',
                    'org_id' => $tech?->id,
                ],
                [
                    'email' => 'aluno.epsilon@plataforma.com',
                    'name' => 'Aluno Epsilon',
                    'cpf' => '777.777.777-77',
                    'org_id' => $acme?->id,
                ],
            ];

            foreach ($alunosData as $data) {
                $aluno = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make('password'),
                        'org_id' => $data['org_id'],
                        'cpf' => $data['cpf'],
                        'status' => 'active',
                    ]
                );
                if (! $aluno->email_verified_at) {
                    $aluno->forceFill(['email_verified_at' => now()])->save();
                }
                if (! $aluno->hasRole(RolesEnum::ALUNO->value)) {
                    $aluno->assignRole(RolesEnum::ALUNO->value);
                }
            }
        });
    }
}
