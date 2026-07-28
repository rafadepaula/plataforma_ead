# **Especificação Técnica 09: Dashboard do Administrador, Analytics e Configurações do Sistema**

---

## **1. Visão Geral e Escopo Técnico**

### **1.1. Resumo do Módulo**
Este módulo engloba as ferramentas essenciais de governança, monitoramento analítico e customização global do sistema pelo Administrador:
1. **Dashboard do Administrador (`/admin/dashboard`)**: Painel gerencial agregando indicadores operacionais em tempo real (total de alunos, matrículas ativas, certificados emitidos, média de notas em avaliações) e gráficos sintéticos de desempenho.
2. **Central de Exportação de Dados em CSV (`/admin/export/{type}`)**: Ferramenta de inteligência operacional projetada especificamente para **hospedagem compartilhada**, utilizando requisições em *streaming* de dados via HTTP com consumo de memória de footprint reduzido ($O(1)$) para evitar estouro de limite de RAM ou timeout PHP durante a geração de grandes relatórios.
3. **Configurações Gerais do Sistema (`/admin/configuracoes`)**: Painel de gerenciamento de parâmetros globais armazenados na tabela `system_settings`, abrangendo parâmetros de disparo de e-mails via SMTP, upload de logos institucionais e imagem de assinatura para certificados, além da chave/salt de hashing do certificado.

---

### **1.2. Mapeamento de Requisitos e Casos de Uso**

* **Requisitos Funcionais (RF):**
  * **RF18 (Dashboard e Relatórios):** Exibir métricas gerenciais e permitir a exportação de dados detalhados em CSV.
* **Requisitos Não-Funcionais (RNF):**
  * **RNF01 & RNF02 (Desempenho em Hospedagem Compartilhada):** Otimização de memória em processamentos assíncronos e relatórios em batch.
* **Casos de Uso (UC):**
  * **UC11 (Painel Gerencial, Dashboard e Relatórios):** Métricas de matrículas, conclusões, notas de questionários e exportação CSV.
  * **UC12 (Configurações Gerais do Sistema e Personalização do Certificado):** Configurar parâmetros SMTP, logos, assinaturas e chaves de validação.

---

## **2. Modelo de Banco de Dados e Migrações Eloquent**

### **2.1. Tabela `system_settings`**

```sql
CREATE TABLE `system_settings` (
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT NULL DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **2.2. Migration Laravel: `database/migrations/2026_01_01_000018_create_system_settings_table.php`**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('setting_key', 50)->primary();
            $table->text('setting_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
```

---

### **2.3. Model Eloquent & Helper Service: `app/Models/SystemSetting.php` e `app/Services/SettingService.php`**

#### **`app/Models/SystemSetting.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class SystemSetting
 * 
 * @property string $setting_key
 * @property string|null $setting_value
 */
class SystemSetting extends Model
{
    protected $table = 'system_settings';
    protected $primaryKey = 'setting_key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'setting_key',
        'setting_value',
    ];
}
```

#### **`app/Services/SettingService.php`**

```php
<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    /**
     * Retrieve a setting value by key with memory caching.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = SystemSetting::find($key);
            return $setting ? $setting->setting_value : $default;
        });
    }

    /**
     * Set/update a setting key and clear cache.
     */
    public function set(string $key, ?string $value): void
    {
        SystemSetting::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );

        Cache::forget("system_setting_{$key}");
    }

    /**
     * Bulk update settings from an associative array.
     */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }
}
```

---

## **3. Arquitetura de Rotas, Controllers e Form Requests**

### **3.1. Definição de Rotas: `routes/web.php`**

```php
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminExportController;
use App\Http\Controllers\Admin\SystemSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard & Analytics
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Exportação em CSV (Streaming)
    Route::get('/export/{type}', [AdminExportController::class, 'export'])->name('export');

    // Configurações do Sistema
    Route::get('/configuracoes', [SystemSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/configuracoes', [SystemSettingsController::class, 'update'])->name('settings.update');
});
```

---

### **3.2. Form Request: `app/Http/Requests/Admin/UpdateSystemSettingsRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            // SMTP Settings
            'mail_host' => ['nullable', 'string', 'max:150'],
            'mail_port' => ['nullable', 'numeric', 'between:1,65535'],
            'mail_username' => ['nullable', 'string', 'max:150'],
            'mail_password' => ['nullable', 'string', 'max:150'],
            'mail_encryption' => ['nullable', 'string', 'in:tls,ssl,none'],
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:150'],

            // Platform branding & Certificate Salt
            'platform_name' => ['required', 'string', 'max:150'],
            'organization_name' => ['required', 'string', 'max:150'],
            'certificate_salt' => ['required', 'string', 'min:16', 'max:64'],

            // Logo & Signature Uploads
            'platform_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'certificate_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
            'signature_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'platform_name.required' => 'O nome da plataforma é obrigatório.',
            'organization_name.required' => 'O nome do conselho/organização é obrigatório.',
            'certificate_salt.required' => 'A chave salt de segurança do certificado é obrigatória.',
            'certificate_salt.min' => 'A chave salt deve conter pelo menos 16 caracteres.',
            'platform_logo.image' => 'O logo da plataforma deve ser uma imagem válida (PNG, JPG, SVG).',
            'platform_logo.max' => 'O tamanho máximo da imagem é de 2MB.',
        ];
    }
}
```

---

### **3.3. Controllers**

#### **`app/Http/Controllers/Admin/AdminDashboardController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        // 1. Cards de Métricas Principais
        $totalStudents = User::where('role', 'student')->count();

        $activeEnrollments = DB::table('course_user')
            ->where('status', 'active')
            ->count();

        $totalCertificates = Certificate::count();

        $averageQuizScore = QuizAttempt::where('is_passed', true)
            ->avg('score_percentage') ?? 0.0;

        // 2. Desempenho Sintético por Curso
        $courseStats = Course::withCount([
            'users as active_students_count' => function ($query) {
                $query->where('status', 'active');
            },
            'certificates as completed_certificates_count'
        ])
        ->get();

        return view('admin.dashboard', compact(
            'totalStudents',
            'activeEnrollments',
            'totalCertificates',
            'averageQuizScore',
            'courseStats'
        ));
    }
}
```

#### **`app/Http/Controllers/Admin/AdminExportController.php`** (Otimizado para Hospedagem Compartilhada)

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExportController extends Controller
{
    /**
     * Stream CSV export with O(1) memory consumption.
     */
    public function export(string $type): StreamedResponse
    {
        $fileName = "relatorio-{$type}-" . date('Y-m-d-His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($type) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            switch ($type) {
                case 'students':
                    $this->exportStudents($handle);
                    break;

                case 'enrollments':
                    $this->exportEnrollments($handle);
                    break;

                case 'grades':
                    $this->exportGrades($handle);
                    break;

                case 'certificates':
                    $this->exportCertificates($handle);
                    break;

                default:
                    fputcsv($handle, ['Erro', 'Tipo de exportacao invalido.']);
                    break;
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportStudents($handle): void
    {
        fputcsv($handle, ['ID', 'Nome', 'E-mail', 'CPF', 'Status', 'Data Cadastro']);

        User::where('role', 'student')
            ->select('id', 'name', 'email', 'cpf', 'status', 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($students) use ($handle) {
                foreach ($students as $student) {
                    fputcsv($handle, [
                        $student->id,
                        $student->name,
                        $student->email,
                        $student->cpf ?? 'N/A',
                        $student->status,
                        $student->created_at->format('d/m/Y H:i'),
                    ]);
                }
            });
    }

    private function exportEnrollments($handle): void
    {
        fputcsv($handle, ['ID Matrícula', 'ID Aluno', 'Aluno', 'ID Curso', 'Curso', 'Status', 'Data Matrícula']);

        DB::table('course_user')
            ->join('users', 'course_user.user_id', '=', 'users.id')
            ->join('courses', 'course_user.course_id', '=', 'courses.id')
            ->select(
                'course_user.id',
                'users.id as user_id',
                'users.name as user_name',
                'courses.id as course_id',
                'courses.title as course_title',
                'course_user.status',
                'course_user.enrolled_at'
            )
            ->orderBy('course_user.id')
            ->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->user_id,
                        $row->user_name,
                        $row->course_id,
                        $row->course_title,
                        $row->status,
                        $row->enrolled_at,
                    ]);
                }
            });
    }

    private function exportGrades($handle): void
    {
        fputcsv($handle, ['ID Tentativa', 'Aluno', 'E-mail', 'Questionário', 'Nota (%)', 'Aprovado?', 'Data Conclusão']);

        QuizAttempt::with(['user:id,name,email', 'quiz:id,title'])
            ->orderBy('id')
            ->chunk(500, function ($attempts) use ($handle) {
                foreach ($attempts as $attempt) {
                    fputcsv($handle, [
                        $attempt->id,
                        $attempt->user->name ?? 'N/A',
                        $attempt->user->email ?? 'N/A',
                        $attempt->quiz->title ?? 'N/A',
                        number_format($attempt->score_percentage, 2, ',', ''),
                        $attempt->is_passed ? 'SIM' : 'NÃO',
                        $attempt->completed_at ? $attempt->completed_at->format('d/m/Y H:i') : 'N/A',
                    ]);
                }
            });
    }

    private function exportCertificates($handle): void
    {
        fputcsv($handle, ['ID Certificado', 'Aluno', 'CPF', 'Curso', 'Hash de Validação', 'Data Emissão']);

        Certificate::with(['user:id,name,cpf', 'course:id,title'])
            ->orderBy('id')
            ->chunk(500, function ($certs) use ($handle) {
                foreach ($certs as $cert) {
                    fputcsv($handle, [
                        $cert->id,
                        $cert->user->name ?? 'N/A',
                        $cert->user->cpf ?? 'N/A',
                        $cert->course->title ?? 'N/A',
                        $cert->validation_hash,
                        $cert->issued_at ? $cert->issued_at->format('d/m/Y H:i') : 'N/A',
                    ]);
                }
            });
    }
}
```

#### **`app/Http/Controllers/Admin/SystemSettingsController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\SettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SystemSettingsController extends Controller
{
    public function __construct(
        protected SettingService $settingService
    ) {}

    public function edit(): View
    {
        $settings = [
            'mail_host' => $this->settingService->get('mail_host', config('mail.mailers.smtp.host')),
            'mail_port' => $this->settingService->get('mail_port', config('mail.mailers.smtp.port')),
            'mail_username' => $this->settingService->get('mail_username', ''),
            'mail_password' => $this->settingService->get('mail_password', ''),
            'mail_encryption' => $this->settingService->get('mail_encryption', 'tls'),
            'mail_from_address' => $this->settingService->get('mail_from_address', config('mail.from.address')),
            'mail_from_name' => $this->settingService->get('mail_from_name', config('mail.from.name')),

            'platform_name' => $this->settingService->get('platform_name', 'Plataforma EAD Eletricistas'),
            'organization_name' => $this->settingService->get('organization_name', 'Conselho Profissional de Eletricistas'),
            'certificate_salt' => $this->settingService->get('certificate_salt', 'DefaultSaltKeySecret123!'),

            'platform_logo_path' => $this->settingService->get('platform_logo_path'),
            'certificate_logo_path' => $this->settingService->get('certificate_logo_path'),
            'signature_image_path' => $this->settingService->get('signature_image_path'),
        ];

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Manipulação dos uploads de imagens/logos
        if ($request->hasFile('platform_logo')) {
            $path = $request->file('platform_logo')->store('settings', 'public');
            $validated['platform_logo_path'] = $path;
        }

        if ($request->hasFile('certificate_logo')) {
            $path = $request->file('certificate_logo')->store('settings', 'public');
            $validated['certificate_logo_path'] = $path;
        }

        if ($request->hasFile('signature_image')) {
            $path = $request->file('signature_image')->store('settings', 'public');
            $validated['signature_image_path'] = $path;
        }

        unset($validated['platform_logo'], $validated['certificate_logo'], $validated['signature_image']);

        $this->settingService->setMany($validated);

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Configurações do sistema atualizadas com sucesso!');
    }
}
```

---

## **4. Interface Visual do Dashboard e Configurações**

### **4.1. Dashboard do Admin: `resources/views/admin/dashboard.blade.php`**

```blade
@extends('layouts.admin')

@section('title', 'Dashboard Gerencial')

@section('content')
<div class="space-y-8">
    <!-- Header com Ajuda Contextual -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#004080]">Dashboard Gerencial & Analytics</h1>
            <p class="text-sm text-[#64748B]">Visão geral de alunos, matrículas, certificados e desempenho.</p>
        </div>
        <x-help-button key="admin.dashboard" />
    </div>

    <!-- Cards de Métricas -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="text-xs font-bold text-[#64748B] uppercase tracking-wider">Total de Alunos</div>
            <div class="text-3xl font-extrabold text-[#004080] mt-2">{{ number_format($totalStudents) }}</div>
            <div class="text-xs text-[#10B981] mt-1 font-semibold">Alunos cadastrados</div>
        </div>

        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="text-xs font-bold text-[#64748B] uppercase tracking-wider">Matrículas Ativas</div>
            <div class="text-3xl font-extrabold text-[#004080] mt-2">{{ number_format($activeEnrollments) }}</div>
            <div class="text-xs text-[#64748B] mt-1">Vínculos em cursos</div>
        </div>

        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="text-xs font-bold text-[#64748B] uppercase tracking-wider">Certificados Emitidos</div>
            <div class="text-3xl font-extrabold text-[#004080] mt-2">{{ number_format($totalCertificates) }}</div>
            <div class="text-xs text-[#10B981] mt-1 font-semibold">Autenticados</div>
        </div>

        <div class="p-6 bg-white border border-gray-200 rounded-xl shadow-sm">
            <div class="text-xs font-bold text-[#64748B] uppercase tracking-wider">Média em Avaliações</div>
            <div class="text-3xl font-extrabold text-[#004080] mt-2">{{ number_format($averageQuizScore, 1) }}%</div>
            <div class="text-xs text-[#64748B] mt-1">Taxa média de acertos</div>
        </div>
    </div>

    <!-- Central de Exportação CSV -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h2 class="text-lg font-bold text-[#004080] mb-2">Central de Exportação de Relatórios (CSV)</h2>
        <p class="text-sm text-[#64748B] mb-6">Baixe arquivos formatados em UTF-8 otimizados para consumo instantâneo no Excel.</p>

        <div class="flex flex-wrap gap-4">
            <a href="{{ route('admin.export', ['type' => 'students']) }}"
               class="px-4 py-2.5 bg-[#004080] text-white text-sm font-bold rounded-lg hover:bg-[#003366] transition-colors shadow">
                📊 Exportar Alunos
            </a>
            <a href="{{ route('admin.export', ['type' => 'enrollments']) }}"
               class="px-4 py-2.5 bg-[#004080] text-white text-sm font-bold rounded-lg hover:bg-[#003366] transition-colors shadow">
                📚 Exportar Matrículas
            </a>
            <a href="{{ route('admin.export', ['type' => 'grades']) }}"
               class="px-4 py-2.5 bg-[#004080] text-white text-sm font-bold rounded-lg hover:bg-[#003366] transition-colors shadow">
                📝 Exportar Notas
            </a>
            <a href="{{ route('admin.export', ['type' => 'certificates']) }}"
               class="px-4 py-2.5 bg-[#004080] text-white text-sm font-bold rounded-lg hover:bg-[#003366] transition-colors shadow">
                🎓 Exportar Certificados
            </a>
        </div>
    </div>
</div>
@endsection
```

---

## **5. Estratégia de Suíte de Testes Automatizados (95%+ Coverage)**

### **5.1. Teste Unitário: `tests/Unit/Services/SettingServiceTest.php`**

```php
<?php

namespace Tests\Unit\Services;

use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_and_gets_system_setting_with_cache(): void
    {
        $service = new SettingService();

        $service->set('platform_name', 'EAD Teste');

        $this->assertEquals('EAD Teste', $service->get('platform_name'));
        $this->assertTrue(Cache::has('system_setting_platform_name'));
    }

    public function test_it_returns_default_when_key_does_not_exist(): void
    {
        $service = new SettingService();

        $this->assertEquals('DefaultVal', $service->get('non_existing_key', 'DefaultVal'));
    }
}
```

---

### **5.2. Teste de Integração: `tests/Feature/AdminExportControllerTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminExportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_stream_csv_export_for_students(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(5)->create(['role' => 'student']);

        $response = $this->actingAs($admin)->get(route('admin.export', ['type' => 'students']));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), 'relatorio-students-'));
    }

    public function test_non_admin_cannot_access_export_route(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('admin.export', ['type' => 'students']));
        $response->assertStatus(403);
    }
}
```

---

## **6. Lista de Tarefas de Desenvolvimento (Checklist)**

- [ ] **Tabela & Migration:** Criar migration `create_system_settings_table` e model `SystemSetting`.
- [ ] **Service de Settings:** Implementar `SettingService` com suporte a Cache do Laravel.
- [ ] **Controllers:** Implementar `AdminDashboardController`, `AdminExportController` (Streaming CSV) e `SystemSettingsController`.
- [ ] **Form Requests:** Criar `UpdateSystemSettingsRequest` com validação de uploads de imagem e parâmetros SMTP.
- [ ] **Views Blade:** Desenvolver `admin/dashboard.blade.php` e `admin/settings/edit.blade.php` com botão `<x-help-button key="..." />`.
- [ ] **Testes Automatizados:** Executar testes unitários e de integração validando 95%+ de cobertura.
