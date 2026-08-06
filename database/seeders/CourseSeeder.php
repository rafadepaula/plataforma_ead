<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use App\Services\YoutubeSanitizerService;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $acme = Organization::where('slug', 'acme-cursos')->first();
        $tech = Organization::where('slug', 'tech-academy')->first();

        if (! $acme || ! $tech) {
            return;
        }

        Course::withoutEvents(function () use ($acme, $tech): void {
            Module::withoutEvents(function () use ($acme, $tech): void {
                Lesson::withoutEvents(function () use ($acme, $tech): void {
                    // Course 1 (Acme Cursos)
                    $course1 = Course::withoutGlobalScopes()->firstOrCreate(
                        ['org_id' => $acme->id, 'title' => 'Desenvolvimento Laravel Avançado'],
                        [
                            'description' => 'Curso completo sobre arquitetura e boas práticas em Laravel.',
                            'workload_hours' => 40,
                            'is_published' => true,
                        ]
                    );

                    $module1 = Module::firstOrCreate(
                        ['course_id' => $course1->id, 'title' => 'Módulo 1: Fundamentos & Arquitetura'],
                        [
                            'description' => 'Introdução aos conceitos de multitenancy e clean code.',
                            'order_index' => 1,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $module1->id, 'title' => 'Introdução à Arquitetura Multitenant'],
                        [
                            'type' => 'content',
                            'content_text' => 'Aprenda como estruturar aplicações multitenant isoladas por org_id no Laravel.',
                            'youtube_url' => app(YoutubeSanitizerService::class)->sanitize('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
                            'order_index' => 1,
                            'is_published' => true,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $module1->id, 'title' => 'Guia em PDF de Boas Práticas'],
                        [
                            'type' => 'content',
                            'content_text' => 'Consulte o material de apoio em PDF para aprofundar os estudos.',
                            'pdf_path' => 'courses/docs/guia-laravel.pdf',
                            'order_index' => 2,
                            'is_published' => true,
                        ]
                    );

                    $module2 = Module::firstOrCreate(
                        ['course_id' => $course1->id, 'title' => 'Módulo 2: Testes Automatizados & TDD'],
                        [
                            'description' => 'Estratégias de teste com PHPUnit e Laravel Dusk.',
                            'order_index' => 2,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $module2->id, 'title' => 'PHPUnit e Laravel Dusk na Prática'],
                        [
                            'type' => 'content',
                            'content_text' => 'Como escrever testes de integração e browser na plataforma.',
                            'order_index' => 1,
                            'is_published' => true,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $module2->id, 'title' => 'Avaliação do Módulo 2'],
                        [
                            'type' => 'quiz',
                            'content_text' => null,
                            'order_index' => 2,
                            'is_published' => true,
                        ]
                    );

                    // Course 2 (Tech Academy)
                    $course2 = Course::withoutGlobalScopes()->firstOrCreate(
                        ['org_id' => $tech->id, 'title' => 'Engenharia de Software e Cloud'],
                        [
                            'description' => 'Domine infraestrutura moderna e desenvolvimento de software.',
                            'workload_hours' => 60,
                            'is_published' => true,
                        ]
                    );

                    $techModule1 = Module::firstOrCreate(
                        ['course_id' => $course2->id, 'title' => 'Módulo 1: Containers e Docker'],
                        [
                            'description' => 'Conceitos e prática de dockerization de aplicações web.',
                            'order_index' => 1,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $techModule1->id, 'title' => 'Docker e Ambiente Laravel Sail'],
                        [
                            'type' => 'content',
                            'content_text' => 'Configurando containers com Laravel Sail para ambiente dev.',
                            'youtube_url' => app(YoutubeSanitizerService::class)->sanitize('https://www.youtube.com/watch?v=dQw4w9WgXcQ'),
                            'order_index' => 1,
                            'is_published' => true,
                        ]
                    );

                    Lesson::firstOrCreate(
                        ['module_id' => $techModule1->id, 'title' => 'Questionário sobre Docker'],
                        [
                            'type' => 'quiz',
                            'content_text' => null,
                            'order_index' => 2,
                            'is_published' => true,
                        ]
                    );

                    // Enrollments
                    $alunoAlpha = User::where('email', 'aluno.alpha@plataforma.com')->first();
                    $alunoBeta = User::where('email', 'aluno.beta@plataforma.com')->first();
                    $alunoGamma = User::where('email', 'aluno.gamma@plataforma.com')->first();
                    $alunoDelta = User::where('email', 'aluno.delta@plataforma.com')->first();
                    $alunoEpsilon = User::where('email', 'aluno.epsilon@plataforma.com')->first();

                    $now = now();

                    if ($alunoAlpha) {
                        $course1->students()->syncWithoutDetaching([
                            $alunoAlpha->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                    }
                    if ($alunoBeta) {
                        $course1->students()->syncWithoutDetaching([
                            $alunoBeta->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                    }
                    if ($alunoGamma) {
                        $course2->students()->syncWithoutDetaching([
                            $alunoGamma->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                    }
                    if ($alunoDelta) {
                        $course2->students()->syncWithoutDetaching([
                            $alunoDelta->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                    }
                    if ($alunoEpsilon) {
                        $course1->students()->syncWithoutDetaching([
                            $alunoEpsilon->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                        $course2->students()->syncWithoutDetaching([
                            $alunoEpsilon->id => ['enrolled_at' => $now, 'status' => 'active', 'progress_percentage' => 0],
                        ]);
                    }
                });
            });
        });
    }
}
