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
     * Seed the two development accounts of "Liga Certo": one organizer
     * (gestor) and one student (aluno), both verified with the shared
     * local password `password`.
     */
    public function run(): void
    {
        $ligaCerto = Organization::where('slug', 'liga-certo')->first();

        if (! $ligaCerto) {
            $this->call(OrganizationSeeder::class);
            $ligaCerto = Organization::where('slug', 'liga-certo')->first();
        }

        User::withoutEvents(function () use ($ligaCerto): void {
            $gestor = User::firstOrCreate(
                ['email' => 'gestor.ligacerto@plataforma.com'],
                [
                    'name' => 'Organizador Liga Certo',
                    'password' => Hash::make('password'),
                    'org_id' => $ligaCerto?->id,
                    'cpf' => '111.111.111-11',
                    'status' => 'active',
                ]
            );
            if (! $gestor->email_verified_at) {
                $gestor->forceFill(['email_verified_at' => now()])->save();
            }
            if (! $gestor->hasRole(RolesEnum::GESTOR->value)) {
                $gestor->assignRole(RolesEnum::GESTOR->value);
            }

            $aluno = User::firstOrCreate(
                ['email' => 'aluno.ligacerto@plataforma.com'],
                [
                    'name' => 'Aluno Liga Certo',
                    'password' => Hash::make('password'),
                    'org_id' => $ligaCerto?->id,
                    'cpf' => '222.222.222-22',
                    'status' => 'active',
                ]
            );
            if (! $aluno->email_verified_at) {
                $aluno->forceFill(['email_verified_at' => now()])->save();
            }
            if (! $aluno->hasRole(RolesEnum::ALUNO->value)) {
                $aluno->assignRole(RolesEnum::ALUNO->value);
            }
        });
    }
}
