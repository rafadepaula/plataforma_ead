<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

/**
 * Master Database Seeder and Environment Orchestrator.
 *
 * Checks current environment: in `production`, executes ONLY baseline seeders
 * (Roles, Admin, System Settings). In non-production, executes the full
 * development & testing seeder suite.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Mandatory Baseline Seeders for ALL environments (Prod & Dev)
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminSeeder::class,
            SystemSettingSeeder::class,
            HelpArticleSeeder::class,
        ]);

        // 2. Production Safety Gate
        if (App::environment('production')) {
            if ($this->command) {
                $this->command->info('Ambiente de PRODUÇÃO detectado: apenas Super Admin, Roles e Settings foram populados.');
            }

            return;
        }

        // 3. Development / Testing / Staging Suite — a single, minimal
        // scenario: one organization, its organizer and student, and one
        // course with three modules, quizzes, enrollment and completion rules.
        if ($this->command) {
            $this->command->info('Ambiente de DESENVOLVIMENTO detectado: populando cenário mínimo de desenvolvimento...');
        }

        $nonProductionSeeders = [
            OrganizationSeeder::class,
            UserSeeder::class,
            CourseSeeder::class,
        ];

        // Gracefully execute only seeders that exist in the system
        $existingSeeders = array_filter(
            $nonProductionSeeders,
            fn (string $class): bool => class_exists($class)
        );

        if (! empty($existingSeeders)) {
            $this->call(array_values($existingSeeders));
        }
    }
}
