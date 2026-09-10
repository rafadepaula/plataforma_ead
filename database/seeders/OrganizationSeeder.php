<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Seed the two development tenants, each with its own portal host and
     * landing blade — the exact hosts `scripts/setup-hosts.sh` maps in
     * `/etc/hosts`. Every other development seeder hangs off of these.
     */
    public function run(): void
    {
        Organization::withoutEvents(function (): void {
            Organization::firstOrCreate(
                ['host' => 'localhost.ligacerto'],
                [
                    'name' => 'Liga Certo',
                    'landing_view' => 'ligacerto',
                    'cnpj' => '12.345.678/0001-90',
                    'logo_path' => null,
                    'status' => 'active',
                ]
            );

            Organization::firstOrCreate(
                ['host' => 'localhost.informatica'],
                [
                    'name' => 'Informática Mais',
                    'landing_view' => 'informatica',
                    'cnpj' => '98.765.432/0001-10',
                    'logo_path' => null,
                    'status' => 'active',
                ]
            );
        });
    }
}
