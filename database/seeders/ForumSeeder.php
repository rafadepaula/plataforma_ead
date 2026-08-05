<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class ForumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * SPEC-16 §2.2 / SPEC-10 — Seeds forum topics (pinned and standard) and replies
     * with explicit org_id and withoutEvents() event suppression.
     */
    public function run(): void
    {
        ForumTopic::withoutEvents(function (): void {
            ForumReply::withoutEvents(function (): void {
                $organizations = Organization::query()->get();

                if ($organizations->isEmpty()) {
                    $org = Organization::firstOrCreate(
                        ['slug' => 'acme-cursos'],
                        [
                            'name' => 'Acme Cursos',
                            'cnpj' => '12.345.678/0001-90',
                            'status' => 'active',
                        ]
                    );
                    $organizations = collect([$org]);
                }

                foreach ($organizations as $org) {
                    $course = Course::withoutGlobalScopes()->where('org_id', $org->id)->first()
                        ?? Course::firstOrCreate(
                            ['org_id' => $org->id, 'title' => "Curso com Fórum {$org->name}"],
                            [
                                'description' => 'Curso para discussões e dúvidas',
                                'is_published' => true,
                            ]
                        );

                    $student = User::query()->where('org_id', $org->id)->where('email', 'like', 'aluno%')->first()
                        ?? User::firstOrCreate(
                            ['email' => "aluno.forum.{$org->slug}@example.com"],
                            [
                                'org_id' => $org->id,
                                'name' => "Aluno Fórum {$org->name}",
                                'password' => bcrypt('password'),
                                'status' => 'active',
                                'email_verified_at' => now(),
                            ]
                        );

                    $gestor = User::query()->where('org_id', $org->id)->where('email', 'like', 'gestor%')->first()
                        ?? User::firstOrCreate(
                            ['email' => "gestor.forum.{$org->slug}@example.com"],
                            [
                                'org_id' => $org->id,
                                'name' => "Gestor Fórum {$org->name}",
                                'password' => bcrypt('password'),
                                'status' => 'active',
                                'email_verified_at' => now(),
                            ]
                        );

                    // Pinned Topic by Gestor
                    $pinnedTopic = ForumTopic::firstOrCreate(
                        [
                            'course_id' => $course->id,
                            'title' => "Avisos e Regras do Fórum - {$org->name}",
                        ],
                        [
                            'org_id' => $org->id,
                            'user_id' => $gestor->id,
                            'content' => 'Bem-vindos ao fórum do curso. Por favor mantenham as discussões focadas no conteúdo das aulas.',
                            'is_pinned' => true,
                        ]
                    );

                    // Standard Topic by Student
                    $questionTopic = ForumTopic::firstOrCreate(
                        [
                            'course_id' => $course->id,
                            'title' => "Dúvida sobre a Instalação e Ambiente - {$org->name}",
                        ],
                        [
                            'org_id' => $org->id,
                            'user_id' => $student->id,
                            'content' => 'Gostaria de saber quais requisitos de sistema são necessários para acompanhar as aulas práticas.',
                            'is_pinned' => false,
                        ]
                    );

                    // Reply on Student's topic
                    ForumReply::firstOrCreate(
                        [
                            'topic_id' => $questionTopic->id,
                            'user_id' => $gestor->id,
                        ],
                        [
                            'content' => 'Olá! Você precisará apenas do Docker e PHP 8.2+ instalado em sua máquina local.',
                        ]
                    );

                    // Reply on Pinned topic
                    ForumReply::firstOrCreate(
                        [
                            'topic_id' => $pinnedTopic->id,
                            'user_id' => $student->id,
                        ],
                        [
                            'content' => 'Ciente das regras! Obrigado.',
                        ]
                    );
                }
            });
        });
    }
}
