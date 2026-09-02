<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Seed the development organization: a single "Liga Certo" tenant
     * that every other development seeder hangs off of.
     */
    public function run(): void
    {
        Organization::withoutEvents(function (): void {
            Organization::firstOrCreate(
                ['slug' => 'liga-certo'],
                [
                    'name' => 'Liga Certo',
                    'cnpj' => '12.345.678/0001-90',
                    'logo_path' => null,
                    'status' => 'active',
                ]
            );
        });
    }
}
