<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::withoutEvents(function (): void {
            Organization::firstOrCreate(
                ['slug' => 'acme-cursos'],
                [
                    'name' => 'Acme Cursos',
                    'cnpj' => '12.345.678/0001-90',
                    'logo_path' => null,
                    'status' => 'active',
                ]
            );

            Organization::firstOrCreate(
                ['slug' => 'tech-academy'],
                [
                    'name' => 'Tech Academy',
                    'cnpj' => '98.765.432/0001-10',
                    'logo_path' => null,
                    'status' => 'active',
                ]
            );
        });
    }
}
