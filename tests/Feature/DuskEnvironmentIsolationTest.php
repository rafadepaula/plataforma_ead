<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards / §2.1: the Dusk suite must run against a dedicated
 * `.env.dusk.local` file pointing at the isolated `testing` MySQL database,
 * never against the developer's `.env` / `plataforma_ead` database.
 */
class DuskEnvironmentIsolationTest extends TestCase
{
    public function test_env_dusk_local_file_exists(): void
    {
        $this->assertFileExists(base_path('.env.dusk.local'));
    }

    public function test_env_dusk_example_file_exists(): void
    {
        $this->assertFileExists(base_path('.env.dusk.example'));
    }

    public function test_env_dusk_local_points_to_dedicated_testing_database(): void
    {
        $values = $this->parseEnvFile(base_path('.env.dusk.local'));

        $this->assertSame('mysql', $values['DB_CONNECTION'] ?? null);
        $this->assertSame('testing', $values['DB_DATABASE'] ?? null);
        $this->assertSame('mysql', $values['DB_HOST'] ?? null);
    }

    public function test_env_dusk_local_points_to_selenium_driver(): void
    {
        $values = $this->parseEnvFile(base_path('.env.dusk.local'));

        $this->assertSame('http://selenium:4444/wd/hub', $values['DUSK_DRIVER_URL'] ?? null);
    }

    public function test_env_dusk_local_has_required_keys(): void
    {
        $values = $this->parseEnvFile(base_path('.env.dusk.local'));

        $requiredKeys = [
            'APP_NAME',
            'APP_ENV',
            'APP_KEY',
            'APP_DEBUG',
            'APP_URL',
            'DUSK_DRIVER_URL',
            'DB_CONNECTION',
            'DB_HOST',
            'DB_PORT',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'SESSION_DRIVER',
            'SESSION_LIFETIME',
            'QUEUE_CONNECTION',
            'CACHE_STORE',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $values, "Missing key {$key} in .env.dusk.local");
        }

        $this->assertSame('dusk', $values['APP_ENV']);
    }

    public function test_env_dusk_example_does_not_leak_a_shared_app_key(): void
    {
        $values = $this->parseEnvFile(base_path('.env.dusk.example'));

        $this->assertSame('', $values['APP_KEY'] ?? '', 'APP_KEY should be blank in the shareable example file.');
    }

    public function test_compose_file_provisions_the_dedicated_testing_database(): void
    {
        $compose = file_get_contents(base_path('compose.yaml'));

        $this->assertStringContainsString(
            './vendor/laravel/sail/database/mysql/create-testing-database.sh:/docker-entrypoint-initdb.d/10-create-testing-database.sh',
            $compose,
            'compose.yaml must mount the Sail create-testing-database.sh script on the mysql service.'
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseEnvFile(string $path): array
    {
        $values = [];

        foreach (file($path) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"");
        }

        return $values;
    }
}
