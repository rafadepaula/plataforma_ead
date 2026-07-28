# **Especificação Técnica 05: Motor de Questionários, Provas e Avaliações Interativas**

---

## **1. Visão Geral e Objetivos do Módulo**

O **Motor de Questionários, Provas e Avaliações Interativas** fornece a infraestrutura pedagógica para medir a absorção de conhecimento dos alunos na Plataforma EAD. Ele permite que Administradores construam provas objetivas e dissertativas vinculadas a lições do tipo `quiz`, configurando critérios de aprovação, tentativas e exibição de gabarito. Para os Alunos, disponibiliza um player interativo com envio assíncrono (AJAX), computação imediata do resultado e controle estrito de refaça.

### **1.1. Requisitos Funcionais (RF) e Regras de Negócio (RN) Cobertos**

* **RF08 (Gestão de Questionários pelo Admin):** Permitir a criação e edição de questionários vinculados a lições, definindo título, instruções, flag de permissão para refazer (`allow_retries`), exibição de gabarito (`show_correct_answers`), nota mínima de aprovação (`min_score_percentage`), questões e alternativas.
* **RF09 (Execução de Questionários pelo Aluno):** Exibir formulário interativo de prova para o aluno, capturar respostas, registrar o tempo de resposta, calcular a nota percentual final e exibir o resultado com gabarito condicional.
* **RN02 (Cálculo da Nota do Questionário):** O percentual de acerto é determinado por `(questões corretas / total de questões da prova) * 100`.
* **RN03 (Repetição de Questionários / Retries):** Se `allow_retries` for `false`, o aluno só pode submeter o questionário uma única vez. Novas tentativas são bloqueadas preservando o histórico da tentativa original. Se `true`, o aluno pode refazer o teste caso não tenha sido aprovado ou conforme regras do sistema.
* **RN04 (Exibição Condicional do Gabarito):** O gabarito com a indicação das alternativas corretas só é apresentado na tela de resultado se `show_correct_answers` estiver marcado como `true`.
* **UC14 (Gestão e Aplicação de Questionários e Avaliações):** Fluxos completos do Admin cadastrando a avaliação e do Aluno respondendo e visualizando sua nota e status de aprovação.

---

## **2. Modelo de Dados e Esquema Relacional (DDL)**

O motor utiliza 5 tabelas relacionais dedicadas: `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts` e `quiz_answers`.

```
  +------------------+        +---------------------+        +--------------------+
  |     lessons      |        |       quizzes       |        |   quiz_questions   |
  +------------------+        +---------------------+        +--------------------+
  | id               |<------1| id                  |<------1| id                 |
  | type = 'quiz'    |        | lesson_id (FK)      |        | quiz_id (FK)       |
  +------------------+        | allow_retries (bool)|        | question_text      |
                              | show_correct_answers|        | type (enum)        |
                              | min_score_percentage|        | order_index        |
                              +---------------------+        +--------------------+
                                         |                              |
                                        1|                             1|
                                         v                              v
  +------------------+        +---------------------+        +--------------------+
  |      users       |        |    quiz_attempts    |        |    quiz_options    |
  +------------------+        +---------------------+        +--------------------+
  | id               |<------1| id                  |        | id                 |
  +------------------+        | quiz_id (FK)        |        | question_id (FK)   |
                              | user_id (FK)        |<---+   | option_text        |
                              | score_percentage    |    |   | is_correct (bool)  |
                              | is_passed (bool)    |    |   +--------------------+
                              | completed_at (dt)   |    |              
                              +---------------------+    |              
                                         |               |              
                                        1|               |              
                                         v               |              
                              +---------------------+    |              
                              |    quiz_answers     |    |              
                              +---------------------+    |              
                              | id                  |    |              
                              | attempt_id (FK)     |----+              
                              | question_id (FK)    |                   
                              | selected_option_ids |                   
                              | essay_answer (text) |                   
                              | is_correct (bool)   |                   
                              +---------------------+                   
```

### **2.1. Migrations em Código PHP**

#### `database/migrations/2026_01_01_000007_create_quizzes_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->boolean('allow_retries')->default(true);
            $table->boolean('show_correct_answers')->default(true);
            $table->decimal('min_score_percentage', 5, 2)->default(70.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
```

#### `database/migrations/2026_01_01_000008_create_quiz_questions_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('type', ['single_choice', 'multiple_choice', 'essay'])->default('single_choice');
            $table->integer('order_index')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
```

#### `database/migrations/2026_01_01_000009_create_quiz_options_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_options');
    }
};
```

#### `database/migrations/2026_01_01_000010_create_quiz_attempts_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('score_percentage', 5, 2);
            $table->boolean('is_passed')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('completed_at');
            $table->timestamps();

            $table->index(['quiz_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
```

#### `database/migrations/2026_01_01_000011_create_quiz_answers_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->json('selected_option_ids')->nullable();
            $table->text('essay_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
```

---

### **2.2. Eloquent Models**

#### `app/Models/Quiz.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'instructions',
        'allow_retries',
        'show_correct_answers',
        'min_score_percentage',
    ];

    protected function casts(): array
    {
        return [
            'allow_retries' => 'boolean',
            'show_correct_answers' => 'boolean',
            'min_score_percentage' => 'decimal:2',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order_index', 'asc');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
```

---

## **3. Gestão de Questionários pelo Administrador**

O Administrador é responsável por cadastrar a estrutura do questionário, suas questões e opções de resposta.

### **3.1. Form Request de Validação: `App\Http\Requests\Admin\StoreQuizRequest`**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string'],
            'allow_retries' => ['required', 'boolean'],
            'show_correct_answers' => ['required', 'boolean'],
            'min_score_percentage' => ['required', 'numeric', 'between:0,100'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.question_text' => ['required', 'string'],
            'questions.*.type' => ['required', 'in:single_choice,multiple_choice,essay'],
            'questions.*.options' => ['required_if:questions.*.type,single_choice,multiple_choice', 'array', 'min:2'],
            'questions.*.options.*.option_text' => ['required', 'string'],
            'questions.*.options.*.is_correct' => ['required', 'boolean'],
        ];
    }
}
```

---

### **3.2. Admin Controller: `App\Http\Controllers\Admin\QuizController`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuizRequest;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function create(Lesson $lesson): View
    {
        return view('admin.quizzes.create', compact('lesson'));
    }

    public function store(StoreQuizRequest $request, Lesson $lesson): RedirectResponse
    {
        DB::transaction(function () use ($request, $lesson) {
            $quiz = Quiz::create([
                'lesson_id' => $lesson->id,
                'title' => $request->validated('title'),
                'instructions' => $request->validated('instructions'),
                'allow_retries' => $request->validated('allow_retries'),
                'show_correct_answers' => $request->validated('show_correct_answers'),
                'min_score_percentage' => $request->validated('min_score_percentage'),
            ]);

            foreach ($request->validated('questions') as $index => $qData) {
                $question = $quiz->questions()->create([
                    'question_text' => $qData['question_text'],
                    'type' => $qData['type'],
                    'order_index' => $index,
                ]);

                if ($qData['type'] !== 'essay' && ! empty($qData['options'])) {
                    foreach ($qData['options'] as $optData) {
                        $question->options()->create([
                            'option_text' => $optData['option_text'],
                            'is_correct' => $optData['is_correct'],
                        ]);
                    }
                }
            }
        });

        return redirect()->route('admin.courses.edit', $lesson->module->course_id)
            ->with('success', 'Questionário criado com sucesso!');
    }
}
```

---

## **4. Player Interativo de Questionário do Aluno (Blade + jQuery AJAX)**

### **4.1. Controller do Aluno: `App\Http\Controllers\Student\QuizController`**

```php
<?php

namespace App\Http\Controllers\Student;

use App\Actions\Student\SubmitQuizAttemptAction;
use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizController extends Controller
{
    /**
     * Exibe o Player de Prova para o Aluno.
     */
    public function take(Request $request, Quiz $quiz): View
    {
        $course = $quiz->lesson->module->course;
        $this->authorize('viewContent', $course);

        $user = $request->user();

        // Verifica se o aluno já respondeu e se retries estão desabilitados (RN03)
        $previousAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($previousAttempt && ! $quiz->allow_retries) {
            return view('student.quizzes.result', [
                'quiz' => $quiz,
                'attempt' => $previousAttempt,
                'blockedNewAttempt' => true,
            ]);
        }

        $quiz->load(['questions.options']);

        return view('student.quizzes.take', compact('quiz'));
    }

    /**
     * Processa a submissão das respostas do questionário via AJAX.
     */
    public function submit(Request $request, Quiz $quiz, SubmitQuizAttemptAction $action): JsonResponse
    {
        $course = $quiz->lesson->module->course;
        $this->authorize('viewContent', $course);

        $validated = $request->validate([
            'started_at' => ['required', 'date'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'exists:quiz_questions,id'],
            'answers.*.selected_option_ids' => ['nullable', 'array'],
            'answers.*.essay_answer' => ['nullable', 'string'],
        ]);

        $attempt = $action->execute(
            user: $request->user(),
            quiz: $quiz,
            answersData: $validated['answers'],
            startedAt: $validated['started_at']
        );

        return response()->json([
            'success' => true,
            'redirect_url' => route('student.quiz.result', $attempt->id),
        ]);
    }

    /**
     * Exibe o Resultado da Avaliação e Gabarito Condicional.
     */
    public function result(Request $request, QuizAttempt $attempt): View
    {
        $quiz = $attempt->quiz;
        $course = $quiz->lesson->module->course;
        $this->authorize('viewContent', $course);

        $attempt->load(['answers.question.options']);

        return view('student.quizzes.result', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'blockedNewAttempt' => false,
        ]);
    }
}
```

---

## **5. Motor de Avaliação (`App\Actions\Student\SubmitQuizAttemptAction`)**

Esta Action executa a regra de negócio central de correção automática, cálculo de nota percentual (RN02), controle de retries (RN03) e marcação de progresso do curso.

#### `app/Actions/Student/SubmitQuizAttemptAction.php`

```php
<?php

namespace App\Actions\Student;

use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ValidationException;

class SubmitQuizAttemptAction
{
    public function execute(User $user, Quiz $quiz, array $answersData, string $startedAt): QuizAttempt
    {
        // 1. Validação da Regra de Refaça (RN03)
        if (! $quiz->allow_retries) {
            $hasCompletedAttempt = QuizAttempt::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($hasCompletedAttempt) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'quiz' => 'Este questionário não permite novas tentativas.',
                ]);
            }
        }

        return DB::transaction(function () use ($user, $quiz, $answersData, $startedAt) {
            $questions = $quiz->questions()->with('options')->get();
            $totalQuestions = $questions->count();
            $correctCount = 0;

            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'score_percentage' => 0.00,
                'is_passed' => false,
                'started_at' => Carbon::parse($startedAt),
                'completed_at' => Carbon::now(),
            ]);

            foreach ($questions as $question) {
                $userAnswer = collect($answersData)->firstWhere('question_id', $question->id);
                $selectedOptionIds = $userAnswer['selected_option_ids'] ?? [];
                $essayAnswer = $userAnswer['essay_answer'] ?? null;
                $isCorrect = false;

                if ($question->type === 'single_choice') {
                    $selectedId = $selectedOptionIds[0] ?? null;
                    $correctOption = $question->options->firstWhere('is_correct', true);
                    if ($selectedId && $correctOption && (int) $selectedId === (int) $correctOption->id) {
                        $isCorrect = true;
                    }
                } elseif ($question->type === 'multiple_choice') {
                    $correctIds = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
                    $userSelectedIds = collect($selectedOptionIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

                    if (! empty($correctIds) && $correctIds === $userSelectedIds) {
                        $isCorrect = true;
                    }
                } elseif ($question->type === 'essay') {
                    // Questões dissertativas: no MVP consideram-se preenchidas
                    $isCorrect = ! empty(trim($essayAnswer ?? ''));
                }

                if ($isCorrect) {
                    $correctCount++;
                }

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'selected_option_ids' => $selectedOptionIds,
                    'essay_answer' => $essayAnswer,
                    'is_correct' => $isCorrect,
                ]);
            }

            // 2. Cálculo da Nota Percentual (RN02)
            $scorePercentage = $totalQuestions > 0 
                ? round(($correctCount / $totalQuestions) * 100, 2) 
                : 0.00;

            $isPassed = $scorePercentage >= (float) $quiz->min_score_percentage;

            $attempt->update([
                'score_percentage' => $scorePercentage,
                'is_passed' => $isPassed,
            ]);

            // 3. Atualização do Progresso da Lição no Curso se Aprovado
            if ($isPassed) {
                LessonProgress::updateOrCreate(
                    ['user_id' => $user->id, 'lesson_id' => $quiz->lesson_id],
                    ['is_completed' => true, 'completed_at' => Carbon::now()]
                );
            }

            return $attempt;
        });
    }
}
```

---

## **6. Interactive Views & Gabarito Condicional**

### **6.1. Player do Aluno: `resources/views/student/quizzes/take.blade.php`**

```html
@extends('layouts.app')

@section('title', $quiz->title)

@section('content')
<div class="container py-4">
    <div class="max-w-3xl mx-auto">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h1 class="h4 fw-bold text-dark mb-2">{{ $quiz->title }}</h1>
                @if($quiz->instructions)
                    <p class="text-secondary small mb-3">{{ $quiz->instructions }}</p>
                @endif
                <div class="alert alert-info py-2 px-3 small d-flex justify-content-between align-items-center mb-0">
                    <span><i class="bi bi-info-circle me-1"></i> Nota mínima para aprovação: <strong>{{ $quiz->min_score_percentage }}%</strong></span>
                    <a href="{{ route('help.show', 'student.quiz.take') }}" target="_blank" class="text-decoration-none fw-semibold">Ajuda</a>
                </div>
            </div>
        </div>

        <form id="quizForm" action="{{ route('student.quiz.submit', $quiz->id) }}" method="POST">
            @csrf
            <input type="hidden" name="started_at" value="{{ now()->toDateTimeString() }}">

            @foreach($quiz->questions as $index => $question)
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h2 class="h6 fw-bold text-dark mb-3">
                            <span class="badge bg-primary me-2">{{ $index + 1 }}</span>
                            {{ $question->question_text }}
                        </h2>

                        @if($question->type === 'single_choice')
                            <div class="d-flex flex-column gap-2">
                                @foreach($question->options as $option)
                                    <label class="form-check p-3 border rounded-3 hover-bg-light cursor-pointer">
                                        <input class="form-check-input" type="radio" name="answers[{{ $index }}][selected_option_ids][]" value="{{ $option->id }}" required>
                                        <span class="form-check-label ms-2 text-dark">{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($question->type === 'multiple_choice')
                            <p class="small text-muted mb-2">(Selecione todas as opções corretas)</p>
                            <div class="d-flex flex-column gap-2">
                                @foreach($question->options as $option)
                                    <label class="form-check p-3 border rounded-3 hover-bg-light cursor-pointer">
                                        <input class="form-check-input" type="checkbox" name="answers[{{ $index }}][selected_option_ids][]" value="{{ $option->id }}">
                                        <span class="form-check-label ms-2 text-dark">{{ $option->option_text }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @elseif($question->type === 'essay')
                            <textarea name="answers[{{ $index }}][essay_answer]" class="form-control" rows="4" placeholder="Digite sua resposta..." required></textarea>
                        @endif

                        <input type="hidden" name="answers[{{ $index }}][question_id]" value="{{ $question->id }}">
                    </div>
                </div>
            @endforeach

            <div class="text-end mb-5">
                <button type="submit" id="btnSubmitQuiz" class="btn btn-primary btn-lg px-5 fw-bold rounded-3">
                    <i class="bi bi-send-fill me-2"></i> Finalizar e Enviar Respostas
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    $('#quizForm').on('submit', function(e) {
        e.preventDefault();
        const $btn = $('#btnSubmitQuiz');
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Processando Nota...');

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if(response.success) {
                    window.location.href = response.redirect_url;
                }
            },
            error: function(xhr) {
                alert('Erro ao enviar questionário. Verifique se todas as questões obrigatórias foram respondidas.');
                $btn.prop('disabled', false).html('<i class="bi bi-send-fill me-2"></i> Tentar Novamente');
            }
        });
    });
});
</script>
@endpush
```

---

### **6.2. View de Resultado e Gabarito Condicional: `resources/views/student/quizzes/result.blade.php`**

```html
@extends('layouts.app')

@section('title', 'Resultado do Questionário')

@section('content')
<div class="container py-4">
    <div class="max-w-3xl mx-auto">
        <!-- Card de Resultado Geral -->
        <div class="card border-0 shadow-sm text-center p-4 mb-4">
            <div class="card-body">
                @if($attempt->is_passed)
                    <div class="text-success mb-3 display-3"><i class="bi bi-check-circle-fill"></i></div>
                    <h1 class="h3 fw-bold text-success mb-1">Parabéns! Você foi Aprovado!</h1>
                @else
                    <div class="text-danger mb-3 display-3"><i class="bi bi-x-circle-fill"></i></div>
                    <h1 class="h3 fw-bold text-danger mb-1">Aprovação Não Atingida</h1>
                @endif

                <div class="display-4 fw-bold my-3 text-dark">{{ $attempt->score_percentage }}%</div>
                <p class="text-muted">Nota mínima exigida: {{ $quiz->min_score_percentage }}%</p>

                @if(! $attempt->is_passed && $quiz->allow_retries)
                    <a href="{{ route('student.quiz.take', $quiz->id) }}" class="btn btn-warning fw-bold px-4 py-2 mt-2 rounded-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Tentar Novamente
                    </a>
                @elseif(! $attempt->is_passed && ! $quiz->allow_retries)
                    <div class="alert alert-secondary mt-3">Você utilizou sua tentativa única. Entre em contato com o suporte pedagógico.</div>
                @endif
            </div>
        </div>

        <!-- Exibição de Gabarito Condicional (RN04) -->
        @if($quiz->show_correct_answers)
            <div class="card border-0 shadow-sm p-4 mb-4">
                <h2 class="h5 fw-bold text-dark mb-4"><i class="bi bi-journal-check me-2"></i> Gabarito Detalhado</h2>

                @foreach($attempt->answers as $index => $answer)
                    <div class="p-3 mb-3 border rounded-3 {{ $answer->is_correct ? 'bg-success-subtle border-success' : 'bg-danger-subtle border-danger' }}">
                        <h3 class="h6 fw-bold text-dark mb-2">
                            Questão {{ $index + 1 }}: {{ $answer->question->question_text }}
                        </h3>
                        <p class="small mb-1"><strong>Status:</strong> 
                            <span class="{{ $answer->is_correct ? 'text-success' : 'text-danger' }} fw-bold">
                                {{ $answer->is_correct ? 'Correta' : 'Incorreta' }}
                            </span>
                        </p>

                        @if($answer->question->type !== 'essay')
                            <ul class="list-unstyled mb-0 mt-2 small">
                                @foreach($answer->question->options as $opt)
                                    <li class="{{ $opt->is_correct ? 'fw-bold text-success' : 'text-muted' }}">
                                        <i class="bi {{ $opt->is_correct ? 'bi-check-lg' : 'bi-dash' }} me-1"></i>
                                        {{ $opt->option_text }}
                                        @if($opt->is_correct) <span class="badge bg-success small">Correta</span> @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-light border text-center text-muted p-3">
                <i class="bi bi-eye-slash me-2"></i> A exibição do gabarito detalhado foi desabilitada pelo instrutor para esta avaliação.
            </div>
        @endif
    </div>
</div>
@endsection
```

---

## **7. Central de Ajuda Mapeada**

* **`admin.quizzes.edit`**: "Como configurar nota mínima, liberar tentativas e cadastrar questões objetivas/dissertativas."
* **`student.quiz.take`**: "Instruções para realização da prova, navegação entre questões e submissão."
* **`student.quiz.result`**: "Entendendo seu resultado, nota mínima e visualização do gabarito."

---

## **8. Estratégia Completa de Testes Automatizados (Target: 95%+ Cobertura)**

### **8.1. Teste Unitário: `tests/Unit/Actions/SubmitQuizAttemptActionTest.php`**

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\Student\SubmitQuizAttemptAction;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmitQuizAttemptActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_100_percent_score_when_all_answers_are_correct(): void
    {
        $user = User::factory()->create();
        $quiz = Quiz::factory()->create(['min_score_percentage' => 70.00, 'allow_retries' => true]);

        $question = QuizQuestion::factory()->create(['quiz_id' => $quiz->id, 'type' => 'single_choice']);
        $correctOpt = $question->options()->create(['option_text' => 'Correta', 'is_correct' => true]);
        $wrongOpt = $question->options()->create(['option_text' => 'Errada', 'is_correct' => false]);

        $action = new SubmitQuizAttemptAction();
        $attempt = $action->execute(
            user: $user,
            quiz: $quiz,
            answersData: [
                ['question_id' => $question->id, 'selected_option_ids' => [$correctOpt->id]],
            ],
            startedAt: now()->subMinutes(5)->toDateTimeString()
        );

        $this->assertEquals(100.00, $attempt->score_percentage);
        $this->assertTrue($attempt->is_passed);
    }
}
```

---

### **8.2. Teste de Integração: `tests/Feature/Student/QuizSubmissionTest.php`**

```php
<?php

namespace Tests\Feature\Student;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_new_attempt_when_allow_retries_is_false(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $student->courses()->attach($course->id, ['status' => 'active']);

        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'quiz']);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'allow_retries' => false]);

        // Primeira tentativa registrada
        $quiz->attempts()->create([
            'user_id' => $student->id,
            'score_percentage' => 50.00,
            'is_passed' => false,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->postJson(route('student.quiz.submit', $quiz->id), [
            'started_at' => now()->toDateTimeString(),
            'answers' => [],
        ]);

        $response->assertStatus(422);
    }
}
```

---

### **8.3. Teste E2E Dusk: `tests/Browser/StudentQuizFlowDuskTest.php`**

```php
<?php

namespace Tests\Browser;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class StudentQuizFlowDuskTest extends DuskTestCase
{
    use DatabaseMigrations;

    public function test_student_completes_quiz_and_views_results(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $course = Course::factory()->create();
        $student->courses()->attach($course->id, ['status' => 'active']);

        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'quiz']);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'title' => 'Avaliação Final']);

        $question = QuizQuestion::factory()->create(['quiz_id' => $quiz->id, 'question_text' => 'Qual a tensão padrão?']);
        $correctOpt = $question->options()->create(['option_text' => '220V', 'is_correct' => true]);

        $this->browse(function (Browser $browser) use ($student, $quiz) {
            $browser->loginAs($student)
                ->visit("/aluno/quizzes/{$quiz->id}")
                ->assertSee('Avaliação Final')
                ->radio('answers[0][selected_option_ids][]', $correctOpt->id)
                ->click('#btnSubmitQuiz')
                ->waitForText('Parabéns! Você foi Aprovado!')
                ->assertSee('100%');
        });
    }
}
```

---

## **9. Plano Passo a Passo de Tarefas de Desenvolvimento (Task List)**

1. **Estrutura de Dados & Migrations:**
   - [ ] Executar migrations para `quizzes`, `quiz_questions`, `quiz_options`, `quiz_attempts` e `quiz_answers`.
   - [ ] Criar Models Eloquent e declarar relacionamentos e casts.
2. **Gestão de Questionários pelo Admin:**
   - [ ] Criar `StoreQuizRequest` e `UpdateQuizRequest`.
   - [ ] Criar `Admin\QuizController`.
   - [ ] Criar views Blade para criação e edição de provas pelo Admin.
3. **Player & Motor de Avaliação do Aluno:**
   - [ ] Criar `Student\QuizController`.
   - [ ] Criar `SubmitQuizAttemptAction` com regras de retries (RN03) e nota percentual (RN02).
   - [ ] Criar view Blade `student.quizzes.take` com formulário dinâmico e handler jQuery AJAX.
   - [ ] Criar view Blade `student.quizzes.result` com gabarito condicional (`show_correct_answers` - RN04).
4. **Integração de Progresso & Suíte de Testes:**
   - [ ] Garantir que a aprovação no quiz conclua a lição em `lesson_progress`.
   - [ ] Escrever testes unitários `SubmitQuizAttemptActionTest`.
   - [ ] Escrever testes de integração `QuizSubmissionTest`.
   - [ ] Escrever teste E2E Dusk `StudentQuizFlowDuskTest`.
   - [ ] Auditar e validar meta de 95%+ de cobertura de código.
