<?php

namespace Database\Seeders;

use App\Enums\Permissions\RolesEnum;
use App\Models\Credential;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Seed the development accounts of both tenants: organizer, student
     * and professor per portal, all verified with the shared local
     * password `password`. Each account is the `credentials` row of its
     * Organization — the same e-mail could hold accounts in both portals
     * with different passwords.
     */
    public function run(): void
    {
        $ligacerto = Organization::where('host', 'localhost.ligacerto')->first();

        if (! $ligacerto) {
            $this->call(OrganizationSeeder::class);
            $ligacerto = Organization::where('host', 'localhost.ligacerto')->first();
        }

        $informatica = Organization::where('host', 'localhost.informatica')->first();

        $this->seedAccount(
            organization: $ligacerto,
            email: 'gestor.ligacerto@plataforma.com',
            name: 'Organizador Liga Certo',
            role: RolesEnum::GESTOR->value,
            cpf: '11111111111',
        );

        $this->seedAccount(
            organization: $ligacerto,
            email: 'aluno.ligacerto@plataforma.com',
            name: 'Aluno Liga Certo',
            role: RolesEnum::ALUNO->value,
            cpf: '22222222222',
        );

        $this->seedAccount(
            organization: $ligacerto,
            email: 'professor.ligacerto@plataforma.com',
            name: 'Professor Liga Certo',
            role: RolesEnum::PROFESSOR->value,
            cpf: '33333333333',
        );

        if ($informatica) {
            $this->seedAccount(
                organization: $informatica,
                email: 'gestor.informatica@plataforma.com',
                name: 'Organizador Informática Mais',
                role: RolesEnum::GESTOR->value,
                cpf: '44444444444',
            );

            $this->seedAccount(
                organization: $informatica,
                email: 'aluno.informatica@plataforma.com',
                name: 'Aluno Informática Mais',
                role: RolesEnum::ALUNO->value,
                cpf: '55555555555',
            );
        }
    }

    private function seedAccount(
        ?Organization $organization,
        string $email,
        string $name,
        string $role,
        ?string $cpf,
    ): void {
        if (! $organization) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'cpf' => $cpf,
                'email_verified_at' => now(),
            ]
        );

        Credential::firstOrCreate(
            ['user_id' => $user->id, 'org_id' => $organization->id],
            [
                'password' => Hash::make('password'),
                'status' => 'active',
                'remember_token' => Str::random(60),
            ]
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }
    }
}
