# **01. Autenticação, Perfil do Usuário e Gestão de Alunos com Importação em Lote**

---

## **1. Visão Geral & Escopo Funcional**

Este documento estabelece a especificação técnica detalhada para a camada de Autenticação, Segurança de Acesso, Recuperação de Senha, Gerenciamento de Perfil do Usuário, CRUD Administrativo de Alunos, Vinculação Manual de Matrículas e o motor de Importação em Lote via arquivos CSV.

### **1.1. Mapeamento de Requisitos Funcionais & Casos de Uso**
* **RF01:** Permitir login de Administradores e Alunos via e-mail e senha com hash `bcrypt`.
* **RF02:** Enviar e-mail de redefinição de senha com token temporário de uso único via SMTP.
* **RF04:** Permitir ao Admin cadastrar, listar, buscar, editar e inativar/ativar alunos via Blade + jQuery.
* **RF05:** Permitir importação de múltiplos alunos via upload de arquivo CSV com validação detalhada e barra de progresso.
* **RF21:** Permitir ao Administrador vincular ou remover manualmente alunos em um ou mais cursos.
* **UC01:** Autenticar, Encerrar Sessão e Recuperar Senha.
* **UC02:** Gerenciar Perfil do Usuário.
* **UC03:** Gestão de Alunos e Matrículas pelo Administrador.

---

## **2. Autenticação, Segurança & Hashing**

### **2.1. Guard & Credenciais de Acesso**
* **Mecanismo:** Laravel Web Guard estático baseado em sessão HTTP com cookie encriptado sanitizado (`CookieSessionHandler`).
* **Algoritmo de Hash:** `bcrypt` nativo com fator de custo (*cost factor*) 12 em produção.
* **Validação de Status da Conta:**
  * Durante a tentativa de login (`Auth::attempt`), o sistema valida se a coluna `status` do usuário é igual a `'active'`.
  * Se o status for `'inactive'`, o acesso é negado imediatamente com a mensagem de erro em PT-BR: `"Sua conta está inativa. Entre em contato com o suporte."`

### **2.2. Proteção contra Brute-Force (Rate Limiting)**
* **Mecanismo:** Utilização do `Illuminate\Cache\RateLimiter`.
* **Regra:** Máximo de **5 tentativas incorretas por minuto** por combinação de e-mail e endereço IP do cliente.
* **Comportamento:** Excedido o limite, o endpoint bloqueia temporariamente a IP por 60 segundos retornando o erro: `"Muitas tentativas de login. Por favor, tente novamente em X segundos."`

---

## **3. Recuperação de Senha via SMTP & Token Temporário**

### **3.1. Arquitetura do Fluxo de Redefinição**
```
  [Usuário] ---> (Solicita Redefinição com E-mail)
                     |
                     v
  [ForgotPasswordController] ---> Gera Token SHA-256 no banco (password_reset_tokens)
                     |            (expira em 60 minutos, uso único)
                     v
  [ResetPasswordMail] ----------> Disparo de E-mail via SMTP
                     |
                     v
  [Usuário] <--- Clica no Link no E-mail (/redefinir-senha/{token}?email=...)
                     |
                     v
  [ResetPasswordController] ----> Valida Token + Atualiza Senha + Invalida Token
```

---

## **4. CRUD Completo de Alunos & Gestão de Matrículas**

### **4.1. Funcionalidades da Interface Administrativa**
* **Listagem Paginada (`admin.students.index`):**
  * 15 alunos por página com suporte a paginação assíncrona ou tradicional.
  * **Filtros e Buscas:** Campo de busca textual por **Nome**, **E-mail** ou **CPF**, além de select dropdowns para filtrar por **Status** (`active`/`inactive`) e por **Curso Matriculado** (`course_id`).
* **Modal / Formulário de Cadastro (`admin.students.create`):**
  * Campos: Nome Completo, E-mail, CPF, Senha Inicial, Confirmação de Senha e seleção múltipla de Cursos Iniciais (checkboxes).
* **Modal / Formulário de Edição (`admin.students.edit`):**
  * Alteração de Nome, E-mail, CPF, Status (`active`/`inactive`) e redefinição opcional de senha.
* **Modal de Matrículas (`admin.students.enrollments`):**
  * Exibe lista de todos os cursos publicados com status de vínculo (`Matriculado` / `Não Matriculado`).
  * Botões de ação direta via AJAX para **Matricular** ou **Remover Matrícula**.

---

## **5. Importação de Alunos em Lote via CSV (Otimizada para Hospedagem Compartilhada)**

### **5.1. Formato do Arquivo CSV**
* **Codificação:** UTF-8.
* **Delimitadores Aceitos:** Vírgula (`,`) ou Ponto e Vírgula (`;`).
* **Cabeçalho Obrigatório:** `nome,email,cpf,senha,curso_id`
* **Exemplo de Conteúdo:**
  ```csv
  nome,email,cpf,senha,curso_id
  Carlos Silva,carlos.silva@email.com,12345678901,Senha123@,1
  Ana Souza,ana.souza@email.com,98765432100,Senha123@,1
  ```

### **5.2. Arquitetura do `UserImportService` & Processamento em Chunks**
Para evitar estouro de limite de memória (`128M`) e tempo de execução (`60s`) em hospedagens compartilhadas:
1. **Upload Inicial:** O arquivo CSV é enviado temporariamente para `storage/app/tmp/imports/`.
2. **Processamento Paginado via AJAX:** O frontend chama a rota `/admin/alunos/importar/processar` enviando o nome do arquivo temporário, a posição do cursor (linha inicial) e o tamanho do chunk (`chunk_size = 50`).
3. **Leitura com Streaming (`SplFileObject`):** O backend lê exatamente 50 linhas a partir da posição especificada, reduzindo o consumo de memória RAM a menos de 10MB.
4. **Validação Linha a Linha:**
   * Sanitização e validação de algoritmo de CPF brasileiro.
   * Validação de formato de e-mail e senha.
   * **Fluxo Inteligente de Reutilização de Conta (RN09):** Se o e-mail já existir na base, o sistema vincula o aluno ao `curso_id` especificado sem duplicar a conta e sem sobrescrever a senha existente. Se for um e-mail novo, cria o usuário com a senha informada e efetua a matrícula.
5. **Retorno Estruturado JSON:**
   ```json
   {
     "success_count": 48,
     "error_count": 2,
     "current_line": 50,
     "total_lines": 200,
     "is_completed": false,
     "errors": [
       {"line": 12, "email": "invalid-email", "reason": "Formato de e-mail inválido."},
       {"line": 34, "cpf": "11111111111", "reason": "CPF inválido."}
     ]
   }
   ```

---

## **6. Rotas, Form Requests, Controllers & Services**

### **6.1. Definicao de Rotas (`routes/web.php`)**

```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\StudentController;
use App\Http\Controllers\Admin\StudentImportController;

// Rotas Públicas de Autenticação
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);

    Route::get('/esqueceu-senha', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/esqueceu-senha', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');

    Route::get('/redefinir-senha/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/redefinir-senha', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// Encerramento de Sessão
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// Perfil do Usuário Autenticado
Route::middleware('auth')->group(function () {
    Route::get('/perfil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])->name('profile.update');
});

// Painel Administrativo de Alunos
Route::middleware(['auth', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/alunos', [StudentController::class, 'index'])->name('students.index');
    Route::post('/alunos', [StudentController::class, 'store'])->name('students.store');
    Route::get('/alunos/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::put('/alunos/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::post('/alunos/{student}/toggle-status', [StudentController::class, 'toggleStatus'])->name('students.toggle-status');
    
    // Gestão de Matrículas
    Route::post('/alunos/{student}/matriculas', [StudentController::class, 'enroll'])->name('students.enroll');
    Route::delete('/alunos/{student}/matriculas/{course}', [StudentController::class, 'unenroll'])->name('students.unenroll');

    // Importação em Lote CSV
    Route::get('/alunos-importar', [StudentImportController::class, 'index'])->name('students.import.index');
    Route::post('/alunos-importar/upload', [StudentImportController::class, 'upload'])->name('students.import.upload');
    Route::post('/alunos-importar/processar', [StudentImportController::class, 'processChunk'])->name('students.import.process');
});
```

---

### **6.2. Form Requests (Validações & Mensagens PT-BR)**

#### **`StoreStudentRequest.php`**
```php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'max:14', 'unique:users,cpf'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'courses' => ['nullable', 'array'],
            'courses.*' => ['exists:courses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O campo nome é de preenchimento obrigatório.',
            'name.max' => 'O nome não pode ter mais de 150 caracteres.',
            'email.required' => 'O e-mail é obrigatório.',
            'email.email' => 'Informe um endereço de e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado no sistema.',
            'cpf.unique' => 'Este CPF já está cadastrado para outro usuário.',
            'password.required' => 'A senha é obrigatória.',
            'password.min' => 'A senha deve possuir no mínimo 8 caracteres.',
            'password.confirmed' => 'A confirmação de senha não confere.',
            'courses.*.exists' => 'Um dos cursos selecionados é inválido.',
        ];
    }
}
```

---

### **6.3. Controller de Importação CSV (`StudentImportController.php`)**
```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\UserImportService;
use Illuminate\Support\Facades\Storage;

class StudentImportController extends Controller
{
    public function __construct(private UserImportService $importService) {}

    public function index()
    {
        return view('admin.students.import');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']
        ], [
            'csv_file.required' => 'Selecione um arquivo CSV para upload.',
            'csv_file.mimes' => 'O arquivo deve estar no formato CSV.',
            'csv_file.max' => 'O arquivo não pode exceder 10MB.'
        ]);

        $path = $request->file('csv_file')->store('tmp/imports');

        return response()->json([
            'file_path' => $path,
            'filename' => $request->file('csv_file')->getClientOriginalName(),
            'message' => 'Upload concluído com sucesso. Pronto para processar.'
        ]);
    }

    public function processChunk(Request $request)
    {
        $request->validate([
            'file_path' => ['required', 'string'],
            'start_line' => ['required', 'integer', 'min:1'],
            'chunk_size' => ['nullable', 'integer', 'max:200']
        ]);

        $result = $this->importService->processChunk(
            $request->input('file_path'),
            $request->input('start_line', 1),
            $request->input('chunk_size', 50)
        );

        return response()->json($result);
    }
}
```

---

## **7. Interface do Usuário (Blade Views + AJAX jQuery)**

### **7.1. View de Importação CSV com Barra de Progresso AJAX (`resources/views/admin/students/import.blade.php`)**

```html
@extends('layouts.admin')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-primary-brand font-weight-bold">Importação de Alunos em Lote (CSV)</h1>
        <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Voltar para Lista
        </a>
    </div>

    <!-- Card de Upload -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <form id="csvUploadForm" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="csv_file" class="form-label font-weight-bold">Selecione o Arquivo CSV</label>
                    <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                    <small class="form-text text-muted">
                        O arquivo deve ter os campos: <code>nome,email,cpf,senha,curso_id</code>.
                    </small>
                </div>
                <button type="submit" id="btnUpload" class="btn btn-primary-brand">
                    <i class="bi bi-upload"></i> Enviar e Iniciar Importação
                </button>
            </form>
        </div>
    </div>

    <!-- Progress Card (Inicialmente Oculto) -->
    <div class="card shadow-sm border-0 mb-4 d-none" id="progressCard">
        <div class="card-body p-4">
            <h5 class="card-title font-weight-bold mb-3">Processando Importação...</h5>
            <div class="progress mb-3" style="height: 25px;">
                <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary-brand" 
                     role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
            </div>
            <p id="progressStatus" class="text-secondary mb-0">Lendo arquivo...</p>
        </div>
    </div>

    <!-- Tabela de Erros / Resumo -->
    <div class="card shadow-sm border-0 d-none" id="reportCard">
        <div class="card-header bg-white py-3">
            <h5 class="card-title font-weight-bold mb-0 text-primary-brand">Relatório de Processamento</h5>
        </div>
        <div class="card-body p-4">
            <div class="row text-center mb-4">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h3 id="statSuccess" class="text-success font-weight-bold">0</h3>
                        <span class="text-muted">Importados / Matrículas Atualizadas</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded">
                        <h3 id="statErrors" class="text-danger font-weight-bold">0</h3>
                        <span class="text-muted">Falhas Registradas</span>
                    </div>
                </div>
            </div>

            <div id="errorTableContainer" class="d-none">
                <h6 class="font-weight-bold text-danger mb-3">Detalhamento dos Erros:</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Linha</th>
                                <th>E-mail</th>
                                <th>Motivo da Falha</th>
                            </tr>
                        </thead>
                        <tbody id="errorTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let filePath = '';
    let startLine = 1;
    let totalSuccess = 0;
    let totalErrors = 0;
    const chunkSize = 50;

    $('#csvUploadForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);

        $('#btnUpload').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Enviando...');

        $.ajax({
            url: "{{ route('admin.students.import.upload') }}",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                filePath = response.file_path;
                $('#progressCard').removeClass('d-none');
                startLine = 1;
                totalSuccess = 0;
                totalErrors = 0;
                processNextChunk();
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || 'Erro ao realizar upload do arquivo.');
                $('#btnUpload').prop('disabled', false).html('<i class="bi bi-upload"></i> Enviar e Iniciar Importação');
            }
        });
    });

    function processNextChunk() {
        $.ajax({
            url: "{{ route('admin.students.import.process') }}",
            type: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                file_path: filePath,
                start_line: startLine,
                chunk_size: chunkSize
            },
            success: function(res) {
                totalSuccess += res.success_count;
                totalErrors += res.error_count;

                let percent = Math.round((res.current_line / res.total_lines) * 100);
                if (percent > 100) percent = 100;

                $('#progressBar').css('width', percent + '%').text(percent + '%');
                $('#progressStatus').text(`Processado até a linha ${res.current_line} de ${res.total_lines}...`);

                if (res.errors && res.errors.length > 0) {
                    $('#errorTableContainer').removeClass('d-none');
                    res.errors.forEach(err => {
                        $('#errorTableBody').append(`
                            <tr>
                                <td>${err.line}</td>
                                <td>${err.email || 'N/A'}</td>
                                <td>${err.reason}</td>
                            </tr>
                        `);
                    });
                }

                if (!res.is_completed) {
                    startLine = res.next_line;
                    processNextChunk();
                } else {
                    $('#progressBar').removeClass('progress-bar-animated').addClass('bg-success');
                    $('#progressStatus').text('Importação concluída com sucesso!');
                    $('#statSuccess').text(totalSuccess);
                    $('#statErrors').text(totalErrors);
                    $('#reportCard').removeClass('d-none');
                    $('#btnUpload').prop('disabled', false).html('<i class="bi bi-upload"></i> Nova Importação');
                }
            },
            error: function() {
                alert('Ocorreu uma falha no processamento do lote. Tente novamente.');
                $('#btnUpload').prop('disabled', false).html('<i class="bi bi-upload"></i> Tentar Novamente');
            }
        });
    }
});
</script>
@endpush
```

---

## **8. Chaves da Central de Ajuda (Help Center Keys)**

Todas as rotas deste módulo contêm atalho para suporte contextual. O array de chaves registradas para a Central de Ajuda é:

| Rota / Página | Chave de Ajuda (`target_page_key`) | Conteúdo do Artigo de Ajuda |
| :--- | :--- | :--- |
| `/login` | `auth.login` | Instruções de acesso, requisitos de senha, política de bloqueio e links de recuperação. |
| `/esqueceu-senha` | `auth.forgot_password` | Passo a passo para redefinir a senha via e-mail e tempo de validade do token. |
| `/perfil` | `user.profile` | Guia de atualização de dados pessoais, alteração de e-mail e alteração de senha segura. |
| `/admin/alunos` | `admin.students.index` | Como buscar, filtrar por curso/status, cadastrar novo aluno e gerenciar matrículas. |
| `/admin/alunos-importar` | `admin.students.import` | Especificação da planilha CSV, formato de colunas, delimitadores e interpretação do relatório de erros. |

---

## **9. Suíte de Testes Automatizados (Target 95% Cobertura)**

### **9.1. Matriz de Cenários de Teste**

```
tests/
├── Unit/
│   ├── UserTest.php                  # Testes de roles, scopes e helper methods (isAdmin, isEnrolledIn)
│   ├── UserImportServiceTest.php     # Validação de parsing CSV, chunks, erros de formato e de CPF
├── Feature/
│   ├── Auth/
│   │   ├── AuthenticationTest.php    # Login sucesso, credenciais inválidas, conta inativa, rate limiting
│   │   └── PasswordResetTest.php     # Solicitação token, envio e-mail, redefinição senha
│   ├── ProfileTest.php               # Atualização perfil, validação e-mail único, confirmação senha
│   └── Admin/
│       ├── StudentManagementTest.php # CRUD completo de alunos, filtros, toggle-status, matriculas
│       └── StudentImportTest.php     # Upload CSV, processamento assíncrono em chunks, logs de erro
└── Browser/ (Dusk E2E)
    └── StudentAdminDuskTest.php      # Teste E2E de criação de aluno e upload de arquivo CSV com barra de progresso
```

#### **Exemplo de Teste de Integração: `StudentImportTest.php`**
```php
namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->course = Course::factory()->create();
    }

    public function test_admin_can_upload_valid_csv_file(): void
    {
        Storage::fake('local');

        $csvContent = "nome,email,cpf,senha,curso_id\n" .
                      "Aluno Teste 1,aluno1@test.com,12345678909,Senha123@,{$this->course->id}\n";

        $file = UploadedFile::fake()->createWithContent('alunos.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.upload'), [
                'csv_file' => $file
            ]);

        $response->assertOk()
            ->assertJsonStructure(['file_path', 'filename', 'message']);
    }

    public function test_import_process_creates_student_and_enrollment(): void
    {
        Storage::fake('local');

        $csvContent = "nome,email,cpf,senha,curso_id\n" .
                      "Aluno Teste 1,aluno1@test.com,12345678909,Senha123@,{$this->course->id}\n";

        $filePath = Storage::disk('local')->put('tmp/imports/test.csv', $csvContent);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.students.import.process'), [
                'file_path' => $filePath,
                'start_line' => 1,
                'chunk_size' => 50
            ]);

        $response->assertOk()
            ->assertJson([
                'success_count' => 1,
                'error_count' => 0,
                'is_completed' => true
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'aluno1@test.com',
            'role' => 'student'
        ]);

        $student = User::where('email', 'aluno1@test.com')->first();
        $this->assertDatabaseHas('course_user', [
            'user_id' => $student->id,
            'course_id' => $this->course->id,
            'status' => 'active'
        ]);
    }
}
```

---

## **10. Plano Passo a Passo de Implementação (Task List)**

1. **Database & Models:**
   - [ ] Executar Migration da tabela `users` e `course_user`.
   - [ ] Atualizar Eloquent Model `User.php` com `$fillable`, `$hidden`, `$casts` e relacionamentos `courses()`, `activeCourses()`.
   - [ ] Criar `UserFactory.php` com estados `admin()`, `student()`, `active()`, `inactive()`.

2. **Autenticação & Senha:**
   - [ ] Criar `LoginRequest.php`, `ForgotPasswordRequest.php`, `ResetPasswordRequest.php`.
   - [ ] Implementar `LoginController.php`, `ForgotPasswordController.php`, `ResetPasswordController.php`.
   - [ ] Criar Mailable `ResetPasswordMail.php` com template Blade responsivo.
   - [ ] Montar views `auth.login`, `auth.forgot-password`, `auth.reset-password`.

3. **Gerenciamento de Perfil:**
   - [ ] Criar `UpdateProfileRequest.php` validando nome, e-mail único e senha atual.
   - [ ] Implementar `ProfileController.php` e a view `profile.edit.blade.php`.

4. **CRUD de Alunos & Matrículas:**
   - [ ] Criar `StoreStudentRequest.php` e `UpdateStudentRequest.php`.
   - [ ] Implementar `StudentController.php` com métodos `index`, `store`, `update`, `toggleStatus`, `enroll`, `unenroll`.
   - [ ] Montar view `admin.students.index.blade.php` com modais de cadastro/edição/matrícula e dinamismo jQuery.

5. **Importação CSV em Lote:**
   - [ ] Criar o serviço `UserImportService.php` com métodos de parsing, validação de linha e chunking.
   - [ ] Criar `StudentImportController.php` com endpoints `upload` e `processChunk`.
   - [ ] Montar a view `admin.students.import.blade.php` com drag-and-drop de arquivo, barra de progresso AJAX e tabela de erros.

6. **Testes & Cobertura:**
   - [ ] Escrever `AuthenticationTest.php`, `PasswordResetTest.php`, `ProfileTest.php`.
   - [ ] Escrever `StudentManagementTest.php` e `StudentImportTest.php`.
   - [ ] Executar `vendor/bin/sail artisan test --coverage --min=95` e garantir aprovação de 100% dos cenários.
