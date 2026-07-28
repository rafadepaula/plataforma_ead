# **Especificação Técnica 04: Experiência de Aprendizagem do Aluno e Registro de Progresso**

---

## **1. Visão Geral e Objetivos do Módulo**

O módulo de **Experiência de Aprendizagem do Aluno e Registro de Progresso** é o núcleo pedagógico da Plataforma EAD. Ele provê a interface de consumo de conteúdos multimídia (vídeo YouTube sanitizado, arquivos PDF integrados, conteúdos Rich Text) para alunos matriculados, além de rastrear e atualizar assincronamente a evolução pedagógica do aluno em tempo real.

### **1.1. Requisitos Funcionais (RF) e Regras de Negócio (RN) Cobertos**

* **RF13 (Player de Vídeo Responsivo):** Renderizar player do YouTube incorporado com parâmetros de controle restritos (`?modestbranding=1&rel=0&controls=1&disablekb=1&enablejsapi=1`) na área de estudos.
* **RF14 (Leitor de PDF Integrado):** Exibir arquivos PDF diretamente na página da aula através de visualizador HTML5/iframe seguro, disponibilizando botão para download validado.
* **RF15 (Registro de Progresso):** Registrar a conclusão de lições e recalcular a porcentagem global de progresso do curso em tempo real via requisições AJAX.
* **RF20 (Controle Rígido de Acesso por Matrícula):** Garantir que o aluno visualize e acesse **exclusivamente** os cursos nos quais possui vínculo ativo na tabela `course_user`.
* **RN08 (Restrição Estrita de Matrícula):** Alunos sem registro ativo na tabela `course_user` para o curso solicitado recebem status **HTTP 403 (Forbidden)** ao tentar acessar páginas, lições, downloads de PDF ou fórum do curso.
* **UC07 (Consumo de Aulas e Navegação Restrita por Matrícula):** Visualização da estrutura de módulos/aulas e consumo de mídias por alunos devidamente matriculados.
* **UC08 (Registro e Rastreamento do Progresso do Aluno):** Ação de marcar lição como concluída/pendente com recálculo automático da porcentagem global do curso no frontend.

---

## **2. Modelo de Dados e Relacionamentos Eloquent**

O rastreamento de progresso e o controle de matrículas utilizam as tabelas `courses`, `course_user`, `modules`, `lessons` e `lesson_progress`.

```
  +---------------+        +------------------+        +---------------+
  |    users      |        |   course_user    |        |    courses    |
  +---------------+        +------------------+        +---------------+
  | id            |<------1| user_id (FK)     |    +-->| id            |
  | name          |        | course_id (FK)  |-N---+   | title         |
  | email         |        | status ('active')|        | description   |
  +---------------+        +------------------+        +---------------+
          |                                                    |
          |                                                   1|
          |                                                    v
  +-------------------+    +------------------+        +---------------+
  |  lesson_progress  |    |     lessons      |        |    modules    |
  +-------------------+    +------------------+        +---------------+
  | id                |    | id               |<------1| id            |
  | user_id (FK)      |--->| module_id (FK)   |        | course_id (FK)|
  | lesson_id (FK)    |    | title            |        | title         |
  | is_completed (bool)    | type ('content') |        | order_index   |
  | completed_at (dt) |    | youtube_url      |        +---------------+
  +-------------------+    | pdf_path         |
                           | content_text     |
                           | order_index      |
                           +------------------+
```

### **2.1. Definição das Migrations (DDL)**

#### `database/migrations/2026_01_01_000004_create_course_user_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->dateTime('enrolled_at')->useCurrent();
            $table->enum('status', ['active', 'cancelled', 'completed'])->default('active');
            $table->timestamps();

            $table->unique(['user_id', 'course_id'], 'uk_user_course');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_user');
    }
};
```

#### `database/migrations/2026_01_01_000013_create_lesson_progress_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id'], 'uk_user_lesson_progress');
            $table->index(['user_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
```

---

### **2.2. Eloquent Models**

#### `app/Models/Course.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'workload_hours',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'workload_hours' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order_index', 'asc');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }

    /**
     * Calcula o percentual de progresso de um usuário no curso.
     */
    public function calculateProgressForUser(User $user): float
    {
        $totalLessons = $this->lessons()->where('is_published', true)->count();

        if ($totalLessons === 0) {
            return 0.0;
        }

        $lessonIds = $this->lessons()->where('is_published', true)->pluck('lessons.id');

        $completedCount = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('is_completed', true)
            ->count();

        return round(($completedCount / $totalLessons) * 100, 2);
    }
}
```

#### `app/Models/LessonProgress.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    use HasFactory;

    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'is_completed',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
```

---

## **3. Camada de Autorização e Middleware (Segurança Rígida)**

### **3.1. Middleware `EnsureStudentIsEnrolled`**

Garante que requisições para rotas relativas a um curso específico só continuem se o usuário autenticado for **Admin** ou um **Aluno com matrícula ativa** em `course_user`. Se não estiver matriculado, o middleware aborta a requisição lançando **HTTP 403 Forbidden**.

#### `app/Http/Middleware/EnsureStudentIsEnrolled.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\Course;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentIsEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Administradores têm acesso irrestrito para auditoria e teste
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Obtém o curso da rota (seja por parâmetro de modelo ou ID)
        $course = $request->route('course');

        if (! $course instanceof Course) {
            $courseId = $request->route('course') ?? $request->input('course_id');
            $course = Course::find($courseId);
        }

        if (! $course) {
            abort(Response::HTTP_NOT_FOUND, 'Curso não encontrado.');
        }

        // Valida existência de registro ativo em course_user (RN08)
        $isEnrolled = $user->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('status', 'active')
            ->exists();

        if (! $isEnrolled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Acesso negado. Você não possui matrícula ativa neste curso.',
                ], Response::HTTP_FORBIDDEN);
            }

            abort(Response::HTTP_FORBIDDEN, 'Você não possui matrícula ativa neste curso.');
        }

        return $next($request);
    }
}
```

#### Registro em `bootstrap/app.php`

```php
use App\Http\Middleware\EnsureStudentIsEnrolled;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'enrolled' => EnsureStudentIsEnrolled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

---

### **3.2. Policy `CoursePolicy`**

#### `app/Policies/CoursePolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    /**
     * Determina se o usuário pode visualizar o conteúdo do curso.
     */
    public function viewContent(User $user, Course $course): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        $isEnrolled = $user->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('status', 'active')
            ->exists();

        return $isEnrolled
            ? Response::allow()
            : Response::deny('Você não está matriculado neste curso.', 403);
    }
}
```

---

## **4. Dashboard do Aluno "Meus Cursos" (`/aluno/cursos`)**

### **4.1. Rota e Controller**

* **URL:** `/aluno/cursos`
* **Nome da Rota:** `student.courses.index`
* **Método:** `GET`
* **Controller:** `App\Http\Controllers\Student\CourseController@index`

#### `app/Http/Controllers/Student/CourseController.php`

```php
<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    /**
     * Exibe o dashboard "Meus Cursos" do aluno matriculado.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Carrega apenas cursos com matrícula ativa do aluno, com módulos e lições otimizados
        $courses = $user->courses()
            ->wherePivot('status', 'active')
            ->where('is_published', true)
            ->with(['modules.lessons' => fn ($q) => $q->where('is_published', true)])
            ->get()
            ->map(function ($course) use ($user) {
                $totalLessons = $course->modules->pluck('lessons')->flatten()->count();
                
                $lessonIds = $course->modules->pluck('lessons')->flatten()->pluck('id');
                
                $completedCount = \App\Models\LessonProgress::where('user_id', $user->id)
                    ->whereIn('lesson_id', $lessonIds)
                    ->where('is_completed', true)
                    ->count();

                $progressPercentage = $totalLessons > 0
                    ? round(($completedCount / $totalLessons) * 100, 1)
                    : 0;

                // Busca próxima aula não concluída para o atalho "Continuar Estudando"
                $nextLesson = $course->modules->pluck('lessons')->flatten()
                    ->first(function ($lesson) use ($user) {
                        return ! \App\Models\LessonProgress::where('user_id', $user->id)
                            ->where('lesson_id', $lesson->id)
                            ->where('is_completed', true)
                            ->exists();
                    }) ?? $course->modules->pluck('lessons')->flatten()->first();

                $course->total_lessons = $totalLessons;
                $course->completed_lessons = $completedCount;
                $course->progress_percentage = $progressPercentage;
                $course->next_lesson_id = $nextLesson?->id;

                return $course;
            });

        return view('student.courses.index', compact('courses'));
    }
}
```

---

### **4.2. View Blade: `resources/views/student/courses/index.blade.php`**

```html
@extends('layouts.app')

@section('title', 'Meus Cursos')

@section('content')
<div class="container py-4">
    <!-- Header e Central de Ajuda -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">Meus Cursos</h1>
            <p class="text-muted mb-0">Acompanhe seu progresso e continue seus estudos de capacitação.</p>
        </div>
        <div>
            <a href="{{ route('help.show', 'student.courses.index') }}" class="btn btn-outline-primary btn-sm rounded-pill" target="_blank">
                <i class="bi bi-question-circle me-1"></i> Ajuda nesta tela
            </a>
        </div>
    </div>

    @if($courses->isEmpty())
        <div class="card border-0 shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-journal-x display-4 text-muted mb-3 d-block"></i>
                <h3 class="h5 fw-bold text-dark">Nenhum curso matriculado no momento</h3>
                <p class="text-muted">Entre em contato com a administração ou utilize um link de convite válido para se matricular.</p>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($courses as $course)
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm course-card hover-shadow transition">
                        <div class="card-body d-flex flex-column p-4">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <span class="badge bg-primary-subtle text-primary fw-semibold px-3 py-2 rounded-pill">
                                    <i class="bi bi-clock me-1"></i> {{ $course->workload_hours }}h de carga horária
                                </span>
                                @if($course->progress_percentage == 100)
                                    <span class="badge bg-success-subtle text-success fw-semibold px-2 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> Concluído
                                    </span>
                                @endif
                            </div>

                            <h2 class="h5 fw-bold text-dark mb-2">{{ $course->title }}</h2>
                            <p class="text-secondary small mb-4 flex-grow-1">
                                {{ Str::limit($course->description, 110) }}
                            </p>

                            <!-- Barra de Progresso -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="text-muted">Progresso Geral</span>
                                    <span class="fw-bold text-dark">{{ $course->progress_percentage }}%</span>
                                </div>
                                <div class="progress" style="height: 8px;" role="progressbar" aria-valuenow="{{ $course->progress_percentage }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar bg-warning" style="width: {{ $course->progress_percentage }}%"></div>
                                </div>
                                <div class="text-end small text-muted mt-1">
                                    {{ $course->completed_lessons }} de {{ $course->total_lessons }} aulas concluídas
                                </div>
                            </div>

                            <!-- Botão Continuar Estudando -->
                            @if($course->next_lesson_id)
                                <a href="{{ route('student.classroom.show', ['course' => $course->id, 'lesson' => $course->next_lesson_id]) }}" class="btn btn-primary w-100 fw-semibold rounded-3 py-2">
                                    <i class="bi bi-play-circle-fill me-2"></i> Continuar Estudando
                                </a>
                            @else
                                <a href="{{ route('student.classroom.show', ['course' => $course->id, 'lesson' => $course->modules->first()?->lessons->first()?->id ?? 0]) }}" class="btn btn-outline-primary w-100 fw-semibold rounded-3 py-2">
                                    <i class="bi bi-book me-2"></i> Rever Conteúdo
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
```

---

## **5. Sala de Aula / Player do Aluno (`/aluno/cursos/{course}/aulas/{lesson}`)**

### **5.1. Controller da Sala de Aula**

#### `app/Http/Controllers/Student/ClassroomController.php`

```php
<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\ToggleLessonProgressAction;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassroomController extends Controller
{
    /**
     * Renderiza o Player e Sala de Aula do Aluno.
     */
    public function show(Request $request, Course $course, Lesson $lesson): View
    {
        $this->authorize('viewContent', $course);

        $user = $request->user();

        // Carrega estrutura de módulos e aulas ordenados
        $modules = $course->modules()
            ->with(['lessons' => function ($query) {
                $query->where('is_published', true)->orderBy('order_index', 'asc');
            }])
            ->orderBy('order_index', 'asc')
            ->get();

        // Obtém todas as lições publicadas em array para navegação (Anterior / Próxima)
        $allLessons = $modules->pluck('lessons')->flatten();
        $currentIndex = $allLessons->search(fn ($item) => $item->id === $lesson->id);

        $previousLesson = $currentIndex > 0 ? $allLessons->get($currentIndex - 1) : null;
        $nextLesson = $currentIndex !== false && $currentIndex < $allLessons->count() - 1 
            ? $allLessons->get($currentIndex + 1) 
            : null;

        // IDs de lições concluídas pelo aluno neste curso
        $completedLessonIds = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $allLessons->pluck('id'))
            ->where('is_completed', true)
            ->pluck('lesson_id')
            ->toArray();

        $isCurrentLessonCompleted = in_array($lesson->id, $completedLessonIds);

        // Calcula a porcentagem geral de progresso do curso
        $totalLessonsCount = $allLessons->count();
        $completedCount = count($completedLessonIds);
        $courseProgressPercentage = $totalLessonsCount > 0 
            ? round(($completedCount / $totalLessonsCount) * 100, 1) 
            : 0;

        // Sanitização do ID do YouTube para segurança do iframe
        $youtubeVideoId = $this->extractYoutubeVideoId($lesson->youtube_url);

        return view('student.classroom.show', compact(
            'course',
            'lesson',
            'modules',
            'previousLesson',
            'nextLesson',
            'completedLessonIds',
            'isCurrentLessonCompleted',
            'courseProgressPercentage',
            'totalLessonsCount',
            'completedCount',
            'youtubeVideoId'
        ));
    }

    /**
     * Alterna assincronamente a conclusão da lição via AJAX.
     */
    public function toggleProgress(Request $request, Lesson $lesson, ToggleLessonProgressAction $action): JsonResponse
    {
        $course = $lesson->module->course;
        $this->authorize('viewContent', $course);

        $result = $action->execute($request->user(), $lesson);

        return response()->json($result);
    }

    /**
     * Download seguro do arquivo PDF associado à lição.
     */
    public function downloadPdf(Request $request, Lesson $lesson): BinaryFileResponse
    {
        $course = $lesson->module->course;
        $this->authorize('viewContent', $course);

        if (! $lesson->pdf_path || ! Storage::disk('public')->exists($lesson->pdf_path)) {
            abort(404, 'Arquivo PDF não encontrado.');
        }

        $filePath = Storage::disk('public')->path($lesson->pdf_path);

        return response()->download($filePath, basename($lesson->pdf_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Helper de extração e sanitização do Video ID do YouTube.
     */
    private function extractYoutubeVideoId(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([a-zA-Z0-9_-]{11})/', $url, $matches);

        return $matches[1] ?? null;
    }
}
```

---

### **5.2. Action de Progresso: `App\Actions\Student\ToggleLessonProgressAction`**

#### `app/Actions/Student/ToggleLessonProgressAction.php`

```php
<?php

namespace App\Actions\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Carbon\Carbon;

class ToggleLessonProgressAction
{
    /**
     * Executa a inversão ou marcação do status de conclusão de uma lição.
     */
    public function execute(User $user, Lesson $lesson): array
    {
        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['is_completed' => false]
        );

        $newCompletedState = ! $progress->is_completed;

        $progress->update([
            'is_completed' => $newCompletedState,
            'completed_at' => $newCompletedState ? Carbon::now() : null,
        ]);

        // Recalcular métricas globais do curso
        $course = $lesson->module->course;
        $allLessonIds = $course->lessons()->where('is_published', true)->pluck('lessons.id');
        $totalLessons = $allLessonIds->count();

        $completedCount = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $allLessonIds)
            ->where('is_completed', true)
            ->count();

        $courseProgressPercentage = $totalLessons > 0 
            ? round(($completedCount / $totalLessons) * 100, 1) 
            : 0;

        return [
            'success' => true,
            'lesson_id' => $lesson->id,
            'is_completed' => $newCompletedState,
            'completed_lessons_count' => $completedCount,
            'total_lessons_count' => $totalLessons,
            'course_progress_percentage' => $courseProgressPercentage,
        ];
    }
}
```

---

### **5.3. View Blade da Sala de Aula: `resources/views/student/classroom/show.blade.php`**

```html
@extends('layouts.app')

@section('title', $lesson->title . ' - ' . $course->title)

@section('content')
<div class="container-fluid p-0 bg-light min-vh-100 d-flex flex-column">
    <!-- Top Header da Sala de Aula -->
    <header class="bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center shadow-sm">
        <div class="d-flex align-items-center gap-3">
            <a href="{{ route('student.courses.index') }}" class="btn btn-outline-light btn-sm rounded-circle" title="Voltar aos Cursos">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h1 class="h6 mb-0 text-white-50">{{ $course->title }}</h1>
                <span class="fw-bold fs-6">{{ $lesson->title }}</span>
            </div>
        </div>

        <!-- Indicador de Progresso Global do Curso -->
        <div class="d-flex align-items-center gap-3">
            <div class="text-end d-none d-md-block">
                <div class="small text-white-50">Progresso do Curso</div>
                <span id="course-progress-text" class="fw-bold text-warning">{{ $courseProgressPercentage }}%</span>
            </div>
            <div class="progress rounded-pill d-none d-md-block" style="width: 120px; height: 10px;">
                <div id="course-progress-bar" class="progress-bar bg-warning" style="width: {{ $courseProgressPercentage }}%"></div>
            </div>
            <a href="{{ route('help.show', 'student.classroom') }}" class="btn btn-sm btn-outline-info rounded-pill" target="_blank">
                <i class="bi bi-question-circle me-1"></i> Ajuda
            </a>
        </div>
    </header>

    <!-- Layout Principal com Sidebar e Área de Conteúdo -->
    <div class="d-flex flex-grow-1 position-relative">
        <!-- Area de Conteúdo Principal -->
        <main class="flex-grow-1 p-4 overflow-auto" style="max-height: calc(100vh - 65px);">
            <div class="max-w-4xl mx-auto">
                <!-- Seção 1: Player de Vídeo YouTube Sanitizado (RF13) -->
                @if($youtubeVideoId)
                    <div class="ratio ratio-16x9 mb-4 rounded-3 overflow-hidden shadow">
                        <iframe 
                            src="https://www.youtube-nocookie.com/embed/{{ $youtubeVideoId }}?modestbranding=1&rel=0&controls=1&disablekb=1&enablejsapi=1" 
                            title="{{ $lesson->title }}" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                            allowfullscreen
                            id="youtube-player">
                        </iframe>
                    </div>
                @endif

                <!-- Seção 2: Conteúdo Rich Text -->
                @if($lesson->content_text)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4 text-dark leading-relaxed">
                            {!! clean($lesson->content_text) !!}
                        </div>
                    </div>
                @endif

                <!-- Seção 3: Leitor de PDF Integrado + Botão Seguro de Download (RF14) -->
                @if($lesson->pdf_path)
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
                            <h2 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-file-earmark-pdf text-danger me-2"></i> Material Complementar (PDF)
                            </h2>
                            <a href="{{ route('student.lessons.pdf.download', $lesson->id) }}" class="btn btn-outline-danger btn-sm rounded-pill">
                                <i class="bi bi-download me-1"></i> Baixar PDF
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="ratio ratio-4x3" style="min-height: 500px;">
                                <iframe src="{{ Storage::url($lesson->pdf_path) }}#toolbar=0" title="PDF Viewer" allowfullscreen></iframe>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Bar de Ações e Conclusão (RF15) -->
                <div class="card border-0 shadow-sm p-3 mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            @if($previousLesson)
                                <a href="{{ route('student.classroom.show', ['course' => $course->id, 'lesson' => $previousLesson->id]) }}" class="btn btn-outline-secondary rounded-3">
                                    <i class="bi bi-arrow-left me-1"></i> Aula Anterior
                                </a>
                            @endif
                        </div>

                        <!-- Botão Marcar como Concluída via AJAX -->
                        <div>
                            <button 
                                id="btn-toggle-progress" 
                                data-lesson-id="{{ $lesson->id }}"
                                data-url="{{ route('student.lessons.progress', $lesson->id) }}"
                                class="btn {{ $isCurrentLessonCompleted ? 'btn-success' : 'btn-outline-success' }} px-4 py-2 fw-bold rounded-3">
                                <i class="bi {{ $isCurrentLessonCompleted ? 'bi-check-circle-fill' : 'bi-circle' }} me-2" id="progress-icon"></i>
                                <span id="progress-text">{{ $isCurrentLessonCompleted ? 'Aula Concluída' : 'Marcar como Concluída' }}</span>
                            </button>
                        </div>

                        <div>
                            @if($nextLesson)
                                <a href="{{ route('student.classroom.show', ['course' => $course->id, 'lesson' => $nextLesson->id]) }}" class="btn btn-primary rounded-3">
                                    Próxima Aula <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Sidebar de Módulos e Aulas -->
        <aside class="bg-white border-start shadow-sm flex-shrink-0 d-none d-lg-block" style="width: 360px; max-height: calc(100vh - 65px); overflow-y: auto;">
            <div class="p-3 border-bottom bg-light">
                <h2 class="h6 fw-bold text-dark mb-0">Conteúdo do Curso</h2>
                <small class="text-muted" id="sidebar-progress-summary">{{ $completedCount }} de {{ $totalLessonsCount }} concluídas</small>
            </div>

            <div class="accordion accordion-flush" id="courseModulesAccordion">
                @foreach($modules as $index => $module)
                    <div class="accordion-item border-bottom">
                        <h2 class="accordion-header" id="heading-{{ $module->id }}">
                            <button class="accordion-button {{ $module->id === $lesson->module_id ? '' : 'collapsed' }} fw-bold text-dark fs-6" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $module->id }}" aria-expanded="{{ $module->id === $lesson->module_id ? 'true' : 'false' }}">
                                Módulo {{ $index + 1 }}: {{ $module->title }}
                            </button>
                        </h2>
                        <div id="collapse-{{ $module->id }}" class="accordion-collapse collapse {{ $module->id === $lesson->module_id ? 'show' : '' }}" data-bs-parent="#courseModulesAccordion">
                            <div class="accordion-body p-0">
                                <div class="list-group list-group-flush">
                                    @foreach($module->lessons as $item)
                                        @php
                                            $isDone = in_array($item->id, $completedLessonIds);
                                            $isActive = $item->id === $lesson->id;
                                        @endphp
                                        <a href="{{ route('student.classroom.show', ['course' => $course->id, 'lesson' => $item->id]) }}" 
                                           class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-3 px-3 {{ $isActive ? 'active bg-primary-subtle text-primary border-primary' : '' }}"
                                           id="sidebar-lesson-{{ $item->id }}">
                                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                                <i class="bi {{ $isDone ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted' }} sidebar-check-icon" id="sidebar-icon-{{ $item->id }}"></i>
                                                <span class="small text-truncate {{ $isActive ? 'fw-bold' : '' }}">{{ $item->title }}</span>
                                            </div>
                                            @if($item->type === 'quiz')
                                                <span class="badge bg-warning-subtle text-warning small">Prova</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/student/classroom.js') }}"></script>
@endpush
```

---

## **6. Motor Frontend jQuery & Atualização em Tempo Real do Progresso**

O arquivo `public/js/student/classroom.js` lida com a requisição assíncrona AJAX, atualizando o DOM instantaneamente sem recarregar a página.

#### `public/js/student/classroom.js`

```javascript
$(document::ready(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#btn-toggle-progress').on('click', function (e) {
        e.preventDefault();

        const $btn = $(this);
        const url = $btn.data('url');
        const lessonId = $btn.data('lesson-id');

        // Impede duplo clique desabilitando temporariamente
        $btn.prop('disabled', true);

        $.ajax({
            url: url,
            type: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            success: function (response) {
                if (response.success) {
                    const isCompleted = response.is_completed;

                    // 1. Atualiza estado e visual do botão principal
                    if (isCompleted) {
                        $btn.removeClass('btn-outline-success').addClass('btn-success');
                        $('#progress-icon').removeClass('bi-circle').addClass('bi-check-circle-fill');
                        $('#progress-text').text('Aula Concluída');
                    } else {
                        $btn.removeClass('btn-success').addClass('btn-outline-success');
                        $('#progress-icon').removeClass('bi-check-circle-fill').addClass('bi-circle');
                        $('#progress-text').text('Marcar como Concluída');
                    }

                    // 2. Atualiza a barra de progresso no topo da página em tempo real
                    const newPercentage = response.course_progress_percentage;
                    $('#course-progress-text').text(newPercentage + '%');
                    $('#course-progress-bar').css('width', newPercentage + '%').attr('aria-valuenow', newPercentage);

                    // 3. Atualiza ícone da lição na sidebar de navegação
                    const $sidebarIcon = $('#sidebar-icon-' + lessonId);
                    if (isCompleted) {
                        $sidebarIcon.removeClass('bi-circle text-muted').addClass('bi-check-circle-fill text-success');
                    } else {
                        $sidebarIcon.removeClass('bi-check-circle-fill text-success').addClass('bi-circle text-muted');
                    }

                    // 4. Atualiza resumo na sidebar
                    $('#sidebar-progress-summary').text(
                        response.completed_lessons_count + ' de ' + response.total_lessons_count + ' concluídas'
                    );
                }
            },
            error: function (xhr) {
                alert('Erro ao atualizar progresso da aula. Tente novamente.');
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });
}));
```

---

## **7. Integração com a Central de Ajuda Integral (RN05)**

O sistema fornece links de ajuda contextual mapeados na tabela `help_articles`:

1. **`student.courses.index`**: "Como navegar no painel Meus Cursos e acompanhar porcentagem global de conclusão."
2. **`student.classroom`**: "Como utilizar o player de vídeo, baixar materiais em PDF e registrar a conclusão de lições."

---

## **8. Estratégia Completa de Testes Automatizados (Target: 95%+ Cobertura)**

### **8.1. Teste Unitário: `tests/Unit/Actions/ToggleLessonProgressActionTest.php`**

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\Student\ToggleLessonProgressAction;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToggleLessonProgressActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_uncompleted_lesson_as_completed_and_calculates_percentage(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson1 = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);
        $lesson2 = Lesson::factory()->create(['module_id' => $module->id, 'is_published' => true]);

        $action = new ToggleLessonProgressAction();
        $result = $action->execute($user, $lesson1);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['is_completed']);
        $this->assertEquals(50.0, $result['course_progress_percentage']);
        $this->assertEquals(1, $result['completed_lessons_count']);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $user->id,
            'lesson_id' => $lesson1->id,
            'is_completed' => true,
        ]);
    }
}
```

---

### **8.2. Teste de Integração: `tests/Feature/Student/CourseAccessTest.php`**

```php
<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CourseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unenrolled_student_gets_403_forbidden_on_classroom(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $response = $this->actingAs($student)->get(route('student.classroom.show', [$course->id, $lesson->id]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_enrolled_student_can_access_classroom(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create(['is_published' => true]);
        $student->courses()->attach($course->id, ['status' => 'active']);

        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);

        $response = $this->actingAs($student)->get(route('student.classroom.show', [$course->id, $lesson->id]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewHas('lesson');
    }
}
```

---

### **8.3. Teste E2E Laravel Dusk: `tests/Browser/StudentClassroomDuskTest.php`**

```php
<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudentClassroomDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_student_can_toggle_lesson_progress_via_ajax_without_page_reload(): void
    {
        $student = User::factory()->create([
            'email' => 'aluno@teste.com',
            'password' => bcrypt('password'),
            'role' => 'student',
        ]);

        $course = Course::factory()->create(['title' => 'Curso de Elétrica Residencial']);
        $student->courses()->attach($course->id, ['status' => 'active']);

        $module = Module::factory()->create(['course_id' => $course->id, 'title' => 'Módulo 1']);
        $lesson = Lesson::factory()->create([
            'module_id' => $module->id,
            'title' => 'Segurança em Instalações',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->browse(function (Browser $browser) use ($student, $course, $lesson) {
            $browser->loginAs($student)
                ->visit("/aluno/cursos/{$course->id}/aulas/{$lesson->id}")
                ->assertSee('Segurança em Instalações')
                ->assertSee('0%')
                ->click('#btn-toggle-progress')
                ->waitForText('Aula Concluída')
                ->assertSeeIn('#course-progress-text', '100%')
                ->assertHasClass("#sidebar-icon-{$lesson->id}", 'bi-check-circle-fill');
        });
    }
}
```

---

## **9. Plano Passo a Passo de Tarefas de Desenvolvimento (Task List)**

1. **Camada de Banco de Dados & Models:**
   - [ ] Executar migrations para `course_user` e `lesson_progress`.
   - [ ] Implementar relacionamentos em `User`, `Course`, `Lesson` e `LessonProgress`.
   - [ ] Implementar o método `calculateProgressForUser` na model `Course`.
2. **Camada de Segurança e Autorização:**
   - [ ] Criar Middleware `EnsureStudentIsEnrolled` e registrá-lo em `bootstrap/app.php`.
   - [ ] Criar Policy `CoursePolicy` com o método `viewContent`.
3. **Dashboard "Meus Cursos":**
   - [ ] Criar `CourseController` no namespace `Student`.
   - [ ] Criar view Blade `resources/views/student/courses/index.blade.php`.
   - [ ] Integrar atalho da Central de Ajuda `student.courses.index`.
4. **Sala de Aula & Player Multimídia:**
   - [ ] Criar `ClassroomController` no namespace `Student`.
   - [ ] Criar Action `ToggleLessonProgressAction`.
   - [ ] Criar view Blade `resources/views/student/classroom/show.blade.php`.
   - [ ] Implementar sanitização do ID do YouTube para parâmetros restritivos do iframe.
   - [ ] Implementar leitor de PDF e rota segura de download.
   - [ ] Criar script frontend `public/js/student/classroom.js` para manipulação AJAX.
   - [ ] Integrar atalho da Central de Ajuda `student.classroom`.
5. **Suíte de Testes Automatizados:**
   - [ ] Escrever teste unitário `ToggleLessonProgressActionTest`.
   - [ ] Escrever testes de integração `CourseAccessTest` (validando resposta HTTP 403) e `ClassroomTest`.
   - [ ] Escrever teste E2E Dusk `StudentClassroomDuskTest`.
   - [ ] Executar suíte e validar cobertura mínima de 95%.
