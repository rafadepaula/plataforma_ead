# **00. Arquitetura Geral, Esquema de Banco de Dados e Guardrails de Qualidade**

---

## **1. Visão Geral da Arquitetura & Diretrizes de Infraestrutura**

### **1.1. Stack Tecnológica Base**
* **Backend:** **Laravel 13** executando em **PHP 8.5 / 8.2+**.
* **Banco de Dados:** **MariaDB 10.4+ / MySQL 8.0+** utilizando a engine **InnoDB** para suporte a transações ACID e restrições de chave estrangeira com integridade referencial.
* **Frontend:**
  * **Blade Templating Engine** para renderização server-side (SSR).
  * **JavaScript ES6+ Vanilla & jQuery 3.7+** para manipulação dinâmica do DOM, chamadas AJAX (`$.ajax` / `fetch`), atualização do fórum de discussão, controle de players e modais.
  * **CSS / Framework UI:** **Bootstrap 5.3** (ou Tailwind CSS via CDN/Vite) parametrizado com tokens do Design System do projeto.
* **Processamento de Documentos:** Pacote `barryvdh/laravel-dompdf` ou `spatie/laravel-pdf` para renderização server-side de certificados em PDF.

### **1.2. Restrições e Ajustes para Hospedagem Compartilhada**
A aplicação é projetada para rodar em um ambiente de **hospedagem compartilhada de baixo custo** (cPanel / Plesk padrão), sem dependência de servidores dedicados, instâncias VPS ou daemons de background persistentes.

* **Drivers de Fila (Queue Driver):**
  * `QUEUE_CONNECTION=sync` por padrão para processamento imediato síncrono.
  * Opcionalmente `QUEUE_CONNECTION=database` com gatilho via cron job executando `php artisan queue:work --once` a cada minuto via scheduler (`routes/console.php`), evitando processos daemons contínuos como Supervisor.
* **Drivers de Sessão e Cache:**
  * `SESSION_DRIVER=database` ou `file` para suportar conexões sem Redis/Memcached.
  * `CACHE_STORE=database` ou `file`.
* **Armazenamento de Arquivos e Uploads:**
  * Armazenamento em `storage/app/public` com link simbólico criado para `public/storage`.
  * **Segurança do Diretório de Publicação:**
    * Arquivo `.htaccess` na raiz do storage público configurado com `Options -Indexes` para prevenir listagem pública de diretórios.
    * Bloqueio de execução de scripts PHP no diretório de uploads via `.htaccess`:
      ```apache
      <FilesMatch "\.(php|php5|php7|php8|phtml|phar)$">
          Order Allow,Deny
          Deny from all
      </FilesMatch>
      ```
* **Limites Globais de Execução e Memória:**
  * Código otimizado para não exceder `memory_limit = 128M` e `max_execution_time = 60s`.
  * Operações de lote (ex: importação CSV de alunos) utilizam streaming e processamento em pequenos chunks (ex: 50 registros por requisição AJAX).

---

## **2. Esquema Completo do Banco de Dados (18 Tabelas)**

Abaixo está a definição formal das 18 tabelas do sistema relacional, com declarações completas do Schema Builder do Laravel, restrições de chaves e índices otimizados.

```
                              +--------------------+
                              |       users        |
                              +--------------------+
                                | (1)           | (1)
                                |               |
                      +---------+               +------------+
                      |                                      |
                      v (N)                                  v (N)
              +---------------+                      +-------------------+
              |  course_user  |                      |   invitation_links|
              +---------------+                      +-------------------+
                      ^ (N)                                  | (N)
                      |                                      v
                      +------------------+                   |
                                         | (1)               v (1)
                              +--------------------+---------+
                              |      courses       |
                              +--------------------+
                                | (1)     | (1)  | (1)
                                |         |      +------------------+
                      +---------+         +------------+            |
                      | (N)                            | (N)        v (N)
                      v                                v       +-------------+
               +--------------+              +--------------+  |certificates |
               |   modules    |              | forum_topics |  +-------------+
               +--------------+              +--------------+
                      | (1)                         | (1)
                      v (N)                         v (N)
               +--------------+              +--------------+
               |   lessons    |              | forum_replies|
               +--------------+              +--------------+
                      | (1)
                      v (1)
               +--------------+
               |   quizzes    |
               +--------------+
                      | (1)
                      v (N)
               +------------------+
               |  quiz_questions  |
               +------------------+
                      | (1)
                      v (N)
               +------------------+
               |   quiz_options   |
               +------------------+
```

---

### **2.1. Tabela: `users`**
Armazena todos os usuários do sistema (Administradores e Alunos).

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('cpf', 14)->nullable()->unique();
            $table->string('password', 255);
            $table->enum('role', ['admin', 'student'])->default('student');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->rememberToken();
            $table->timestamps();

            // Índices
            $table->index(['email', 'status']);
            $table->index(['role', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
```

---

### **2.2. Tabela: `invitation_links`**
Links temporários para auto-cadastro e matrícula vinculada a um curso.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('invitation_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->integer('max_uses')->default(0); // 0 = ilimitado
            $table->integer('current_uses')->default(0);
            $table->dateTime('expires_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            // Índices
            $table->index(['token', 'expires_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('invitation_links');
    }
};
```

---

### **2.3. Tabela: `courses`**
Cursos oferecidos na plataforma EAD.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description');
            $table->integer('workload_hours')->unsigned();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // Índices
            $table->index('is_published');
        });
    }

    public function down(): void {
        Schema::dropIfExists('courses');
    }
};
```

---

### **2.4. Tabela: `course_user` (Matrículas / Enrollments)**
Tabela pivô com restrição de chave única composta entre usuário e curso.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('course_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->dateTime('enrolled_at');
            $table->enum('status', ['active', 'cancelled', 'completed'])->default('active');
            $table->timestamps();

            // Restrição de Chave Única
            $table->unique(['user_id', 'course_id']);
            
            // Índices
            $table->index(['user_id', 'status']);
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('course_user');
    }
};
```

---

### **2.5. Tabela: `modules`**
Módulos de organização dos conteúdos dentro de um curso.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->integer('order_index')->default(0);
            $table->timestamps();

            // Índices
            $table->index(['course_id', 'order_index']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('modules');
    }
};
```

---

### **2.6. Tabela: `lessons`**
Lições multimídia (conteúdo rico, vídeo do YouTube, PDF, imagens ou questionário).

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('modules')->onDelete('cascade');
            $table->string('title', 200);
            $table->enum('type', ['content', 'quiz'])->default('content');
            $table->longText('content_text')->nullable();
            $table->string('youtube_url', 255)->nullable();
            $table->string('pdf_path', 255)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->integer('order_index')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            // Índices
            $table->index(['module_id', 'order_index', 'is_published']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('lessons');
    }
};
```

---

### **2.7. Tabela: `quizzes`**
Questionários de avaliação vinculados a uma lição do tipo `quiz`.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->boolean('allow_retries')->default(true);
            $table->boolean('show_correct_answers')->default(true);
            $table->decimal('min_score_percentage', 5, 2)->default(70.00);
            $table->timestamps();

            // Índices
            $table->index('lesson_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('quizzes');
    }
};
```

---

### **2.8. Tabela: `quiz_questions`**
Questões objetivas ou dissertativas dos questionários.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->text('question_text');
            $table->enum('type', ['single_choice', 'multiple_choice', 'essay']);
            $table->integer('order_index')->default(0);
            $table->timestamps();

            // Índices
            $table->index(['quiz_id', 'order_index']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_questions');
    }
};
```

---

### **2.9. Tabela: `quiz_options`**
Opções de resposta para questões de escolha única ou múltipla.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('quiz_questions')->onDelete('cascade');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            // Índices
            $table->index('question_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_options');
    }
};
```

---

### **2.10. Tabela: `quiz_attempts`**
Tentativas de realização de questionários pelos alunos.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('score_percentage', 5, 2);
            $table->boolean('is_passed')->default(false);
            $table->dateTime('completed_at');
            $table->timestamps();

            // Índices
            $table->index(['user_id', 'quiz_id']);
            $table->index(['quiz_id', 'is_passed']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_attempts');
    }
};
```

---

### **2.11. Tabela: `quiz_answers`**
Respostas individuais enviadas em uma tentativa de questionário.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->onDelete('cascade');
            $table->foreignId('question_id')->constrained('quiz_questions')->onDelete('cascade');
            $table->json('selected_option_ids')->nullable();
            $table->text('essay_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            // Índices
            $table->index(['attempt_id', 'question_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('quiz_answers');
    }
};
```

---

### **2.12. Tabela: `course_completion_rules`**
Regras de conclusão para liberação automática do certificado.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('course_completion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->enum('rule_type', ['content_percentage', 'quiz_score', 'specific_lesson']);
            $table->unsignedBigInteger('target_id')->nullable(); // Guardará lesson_id se especifico
            $table->decimal('required_percentage', 5, 2)->default(100.00);
            $table->timestamps();

            // Índices
            $table->index(['course_id', 'rule_type']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('course_completion_rules');
    }
};
```

---

### **2.13. Tabela: `lesson_progress`**
Registro de progresso e conclusão individual por aula e aluno.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->boolean('is_completed')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            // Chave Única Composta
            $table->unique(['user_id', 'lesson_id']);
            
            // Índices
            $table->index(['user_id', 'is_completed']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('lesson_progress');
    }
};
```

---

### **2.14. Tabela: `forum_topics`**
Tópicos de discussão abertos no fórum específico de um curso.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('forum_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('title', 200);
            $table->text('content');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();

            // Índices
            $table->index(['course_id', 'is_pinned', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('forum_topics');
    }
};
```

---

### **2.15. Tabela: `forum_replies`**
Respostas aos tópicos de discussão do fórum.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('forum_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('forum_topics')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('content');
            $table->timestamps();

            // Índices
            $table->index(['topic_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('forum_replies');
    }
};
```

---

### **2.16. Tabela: `certificates`**
Certificados emitidos após cumprimento dos pré-requisitos.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('course_id')->constrained('courses')->onDelete('restrict');
            $table->string('validation_hash', 64)->unique();
            $table->dateTime('issued_at');
            $table->timestamps();

            // Chave Única Composta
            $table->unique(['user_id', 'course_id']);
            
            // Índices
            $table->index('validation_hash');
        });
    }

    public function down(): void {
        Schema::dropIfExists('certificates');
    }
};
```

---

### **2.17. Tabela: `help_articles`**
Artigos e guias da Central de Ajuda do sistema.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->enum('category', ['aluno', 'admin', 'geral']);
            $table->string('target_page_key', 100);
            $table->longText('content');
            $table->timestamps();

            // Índices
            $table->index('target_page_key');
            $table->index(['category', 'title']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('help_articles');
    }
};
```

---

### **2.18. Tabela: `system_settings`**
Configurações globais do sistema armazenadas em par chave-valor.

```php
return new class extends Migration {
    public function up(): void {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('setting_key', 50)->primary();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('system_settings');
    }
};
```

---

## **3. Definição Detalhada dos Eloquent Models & Relacionamentos**

### **3.1. Model `User`** (`app/Models/User.php`)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'cpf', 'password', 'role', 'status'
    ];

    protected $hidden = [
        'password', 'remember_token'
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // Scopes
    public function scopeStudents(Builder $query): Builder
    {
        return $query->where('role', 'student');
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // Relationships
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_user')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function activeCourses(): BelongsToMany
    {
        return $this->courses()->wherePivot('status', 'active');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    public function forumReplies(): HasMany
    {
        return $this->hasMany(ForumReply::class);
    }

    // Helper Methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isEnrolledIn(int|Course $course): bool
    {
        $courseId = $course instanceof Course ? $course->id : $course;
        
        if ($this->isAdmin()) {
            return true;
        }

        return $this->activeCourses()->where('course_id', $courseId)->exists();
    }
}
```

---

### **3.2. Model `Course`** (`app/Models/Course.php`)
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'workload_hours', 'is_published'
    ];

    protected function casts(): array
    {
        return [
            'workload_hours' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_user')
            ->withPivot(['enrolled_at', 'status'])
            ->withTimestamps();
    }

    public function invitationLinks(): HasMany
    {
        return $this->hasMany(InvitationLink::class);
    }

    public function completionRules(): HasMany
    {
        return $this->hasMany(CourseCompletionRule::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }
}
```

---

### **3.3. Outros Eloquent Models (Resumo de Mapeamento)**
* **`InvitationLink`**: BelongsTo `Course`, BelongsTo `User` (`created_by`).
* **`Module`**: BelongsTo `Course`, HasMany `Lesson` (ordenado por `order_index`).
* **`Lesson`**: BelongsTo `Module`, HasOne `Quiz`, HasMany `LessonProgress`.
* **`Quiz`**: BelongsTo `Lesson`, HasMany `QuizQuestion`, HasMany `QuizAttempt`.
* **`QuizQuestion`**: BelongsTo `Quiz`, HasMany `QuizOption`.
* **`QuizOption`**: BelongsTo `QuizQuestion`.
* **`QuizAttempt`**: BelongsTo `Quiz`, BelongsTo `User`, HasMany `QuizAnswer`.
* **`QuizAnswer`**: BelongsTo `QuizAttempt`, BelongsTo `QuizQuestion`.
* **`CourseCompletionRule`**: BelongsTo `Course`.
* **`LessonProgress`**: BelongsTo `User`, BelongsTo `Lesson`.
* **`ForumTopic`**: BelongsTo `Course`, BelongsTo `User`, HasMany `ForumReply`.
* **`ForumReply`**: BelongsTo `ForumTopic`, BelongsTo `User`.
* **`Certificate`**: BelongsTo `User`, BelongsTo `Course`.
* **`HelpArticle`**: Scope `byKey($key)`, Scope `byCategory($category)`.
* **`SystemSetting`**: Primary Key `setting_key` (string non-incrementing). Helper estático `SystemSetting::get($key, $default)` e `SystemSetting::set($key, $value)`.

---

## **4. Middleware Central & Arquitetura de Autorização**

### **4.1. Middleware `EnsureStudentIsEnrolled`** (`app/Http/Middleware/EnsureStudentIsEnrolled.php`)
Este é o guardrail central de isolamento de conteúdo EAD. Garante que **alunos só acessam conteúdos e fóruns de cursos nos quais possuem matrícula ativa**.

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\ForumTopic;

class EnsureStudentIsEnrolled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Administradores possuem acesso irrestrito
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Tentar resolver o ID do curso a partir do parâmetro da rota
        $courseId = $this->resolveCourseId($request);

        if (!$courseId) {
            abort(404, 'Curso não encontrado.');
        }

        // Validar matrícula ativa na tabela pivô course_user
        $isEnrolled = $user->activeCourses()
            ->where('course_id', $courseId)
            ->exists();

        if (!$isEnrolled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Acesso negado. Você não possui matrícula ativa neste curso.'
                ], 403);
            }

            return redirect()->route('student.courses.index')
                ->with('error', 'Acesso negado. Você não possui matrícula ativa neste curso.');
        }

        return $next($request);
    }

    private function resolveCourseId(Request $request): ?int
    {
        if ($request->route('course')) {
            $course = $request->route('course');
            return $course instanceof Course ? $course->id : (int) $course;
        }

        if ($request->route('lesson')) {
            $lesson = $request->route('lesson');
            if (!($lesson instanceof Lesson)) {
                $lesson = Lesson::find($lesson);
            }
            return $lesson?->module?->course_id;
        }

        if ($request->route('topic')) {
            $topic = $request->route('topic');
            if (!($topic instanceof ForumTopic)) {
                $topic = ForumTopic::find($topic);
            }
            return $topic?->course_id;
        }

        return null;
    }
}
```

---

### **4.2. Base Policies do Laravel**
Cada entidade restrita possui sua respectiva Policy registrada em `App\Policies`.

#### **Exemplo: `CoursePolicy.php`**
```php
namespace App\Policies;

use App\Models\User;
use App\Models\Course;

class CoursePolicy
{
    // Administrador tem permissão total concedida globalmente via Gate::before
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return null;
    }

    public function view(User $user, Course $course): bool
    {
        return $course->is_published && $user->isEnrolledIn($course);
    }

    public function accessForum(User $user, Course $course): bool
    {
        return $course->is_published && $user->isEnrolledIn($course);
    }

    public function generateCertificate(User $user, Course $course): bool
    {
        return $user->isEnrolledIn($course);
    }
}
```

---

## **5. Design System Tokens & Master Layouts**

### **5.1. Design Tokens (CSS Variables)**
Definidos globalmente no cabeçalho do layout principal (`resources/css/app.css`):

```css
:root {
  /* Cores Institucionais */
  --primary-brand: #004080;       /* Azul Elétrico Principal */
  --primary-hover: #003366;
  --secondary-accent: #F7D700;    /* Amarelo/Dourado Destaque */
  --secondary-hover: #D9BD00;
  --accent-gold: #EAB308;

  /* Neutros e Superfícies */
  --bg-neutral: #F8FAFC;          /* Fundo da Plataforma */
  --surface-card: #FFFFFF;        /* Cards e Modais */
  --border-color: #E2E8F0;

  /* Tipografia */
  --text-primary: #1E293B;        /* Títulos e Conteúdo */
  --text-secondary: #64748B;      /* Legendas e Meta Dados */
  --text-muted: #94A3B8;

  /* Status e Qualidade */
  --color-success: #10B981;
  --color-danger: #EF4444;
  --color-warning: #F59E0B;
  --color-info: #3B82F6;

  /* Bordas e Sombras */
  --radius-sm: 0.375rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
```

---

### **5.2. Master Layouts Blade**

1. **`layouts.app`** (`resources/views/layouts/app.blade.php`): Layout base geral com suporte a CSRF meta tag, scripts jQuery/Bootstrap, e contêiner principal.
2. **`layouts.student`** (`resources/views/layouts/student.blade.php`): Layout com barra lateral navegável de módulos/lições, barra superior com porcentagem de progresso geral e atalho contextual para a Central de Ajuda (`/ajuda/{target_page_key}`).
3. **`layouts.admin`** (`resources/views/layouts/admin.blade.php`): Painel administrativo com menu lateral contendo: Dashboard, Alunos (CRUD/Importação), Cursos & Módulos, Relatórios e Configurações do Sistema.
4. **`layouts.guest`** (`resources/views/layouts/guest.blade.php`): Layout limpo sem barra lateral para a Landing Page Pública, Login, Recuperação de Senha, Validação Pública de Certificado e Convites.

---

## **6. Estrutura da Suíte de Testes Automatizados & Guardrails (95%+ Cobertura)**

### **6.1. Requisitos do Guardrail de Qualidade**
* **Meta de Cobertura Mínima:** **95% de linhas executadas** em todos os Controllers, Services, Form Requests, Middlewares e Policies.
* **Comando de Execução:** `vendor/bin/sail artisan test --coverage --min=95`
* **Bloqueio de Deployment (CI/CD):** Pipeline de automação falha imediatamente se a cobertura for < 95% ou se houver qualquer falha em suíte Unitária, de Integração ou E2E Dusk.

### **6.2. Estrutura da Suíte de Testes**
```
tests/
├── Unit/
│   ├── UserTest.php
│   ├── CpfValidationTest.php
│   ├── UserImportServiceTest.php
│   ├── CourseProgressCalculatorTest.php
│   └── CertificateHashGeneratorTest.php
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php
│   │   └── PasswordResetTest.php
│   ├── Admin/
│   │   ├── StudentManagementTest.php
│   │   └── StudentImportTest.php
│   ├── Student/
│   │   ├── CourseAccessGuardrailTest.php
│   │   └── LessonProgressTest.php
│   └── Forum/
│       └── ForumIsolationTest.php
└── Browser/ (Dusk E2E)
    ├── StudentFlowTest.php
    └── CsvImportProgressDuskTest.php
```
