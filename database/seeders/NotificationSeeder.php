<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * SPEC-16 §2.2 / SPEC-13 — Seeds unread and read database notifications
     * for users using deterministic UUIDs and firstOrCreate for idempotency.
     */
    public function run(): void
    {
        $users = User::query()->get();

        if ($users->isEmpty()) {
            $org = Organization::firstOrCreate(
                ['slug' => 'acme-cursos'],
                [
                    'name' => 'Acme Cursos',
                    'cnpj' => '12.345.678/0001-90',
                    'status' => 'active',
                ]
            );

            $user = User::firstOrCreate(
                ['email' => 'aluno.notif@example.com'],
                [
                    'org_id' => $org->id,
                    'name' => 'Aluno Notificações',
                    'password' => bcrypt('password'),
                    'status' => 'active',
                    'email_verified_at' => now(),
                ]
            );

            $users = collect([$user]);
        }

        foreach ($users as $user) {
            // Deterministic UUID for Unread Notification
            $unreadUuid = sprintf('20000000-0000-4000-8000-%012d', $user->id);

            DatabaseNotification::firstOrCreate(
                ['id' => $unreadUuid],
                [
                    'type' => 'App\\Notifications\\CourseNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => [
                        'message' => 'Você tem um novo conteúdo disponível no curso.',
                        'action_url' => '/dashboard',
                    ],
                    'read_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Deterministic UUID for Read Notification
            $readUuid = sprintf('30000000-0000-4000-8000-%012d', $user->id);

            DatabaseNotification::firstOrCreate(
                ['id' => $readUuid],
                [
                    'type' => 'App\\Notifications\\WelcomeNotification',
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => [
                        'message' => 'Bem-vindo à plataforma EAD!',
                        'action_url' => '/dashboard',
                    ],
                    'read_at' => now()->subDay(),
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]
            );
        }
    }
}
