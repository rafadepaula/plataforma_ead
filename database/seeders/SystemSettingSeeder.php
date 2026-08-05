<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

/**
 * SPEC-12 & SPEC-16 §2.2 — seeds global default system settings
 * (`org_id = 0` sentinel) using idempotent `firstOrCreate`.
 */
class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultSettings = [
            'system_name' => config('app.name', 'Plataforma EAD'),
            'smtp_host' => config('mail.mailers.smtp.host', config('mail.host', '127.0.0.1')),
            'smtp_port' => (string) config('mail.mailers.smtp.port', (string) config('mail.port', '1025')),
            'smtp_username' => config('mail.mailers.smtp.username'),
            'smtp_password' => config('mail.mailers.smtp.password'),
            'smtp_encryption' => config('mail.mailers.smtp.encryption', 'tls'),
            'smtp_from_address' => config('mail.from.address', 'noreply@plataforma.com'),
            'smtp_from_name' => config('mail.from.name', config('app.name', 'Plataforma EAD')),
            'signature' => 'Equipe Plataforma EAD',
            'logo_path' => null,
        ];

        foreach ($defaultSettings as $key => $value) {
            SystemSetting::firstOrCreate(
                [
                    'setting_key' => $key,
                    'org_id' => SystemSetting::GLOBAL_ORG_ID,
                ],
                [
                    'setting_value' => $value,
                ]
            );
        }
    }
}
