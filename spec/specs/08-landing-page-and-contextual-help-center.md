# **Especificação Técnica 08: Landing Page Pública e Central de Ajuda Contextual**

---

## **1. Visão Geral e Escopo Técnico**

### **1.1. Resumo do Módulo**
Este módulo implementa a porta de entrada pública da aplicação e o motor universal de suporte contextual ao usuário. Ele é composto por duas grandes estruturas:
1. **Landing Page Pública (`/`)**: Apresenta a plataforma EAD aos eletricistas e visitantes institucionais sem exigir autenticação prévia. Exibe a proposta de valor, os benefícios da capacitação técnica, imagens temáticas e atalhos para login, auto-cadastro via convite e suporte.
2. **Central de Ajuda Integral (`/ajuda` e `/ajuda/{target_page_key}`)**: Cumpre a **RN05 (Cobertura de Ajuda 100%)**. Fornece suporte contextual imediato em todas as rotas operacionais do sistema através do componente Blade reutilizável `<x-help-button key="..." />`, além de permitir a leitura detalhada, busca por palavras-chave e filtragem por perfil (`aluno`, `admin`, `geral`). Inclui também a interface de gerenciamento administrativo (CRUD) de artigos de ajuda.

---

### **1.2. Mapeamento de Requisitos e Casos de Uso**

* **Requisitos Funcionais (RF):**
  * **RF11 (Landing Page Pública):** Página inicial pública (`/`) apresentando o sistema, objetivos institucionais e atalhos para login/ajuda.
  * **RF12 (Central de Ajuda Integral):** Disponibilizar área de ajuda cobrindo 100% das páginas/funcionalidades, com links contextuais presentes em todas as telas.
* **Regras de Negócio (RN):**
  * **RN05 (Cobertura de Ajuda 100%):** Todas as páginas operacionais exibem atalho direcionando para a página correspondente da Central de Ajuda (`/ajuda/{target_page_key}`).
* **Casos de Uso (UC):**
  * **UC13 (Landing Page Pública e Central de Ajuda Integral):** Acesso à página pública e navegação/gestão de artigos de ajuda.

---

## **2. Modelo de Banco de Dados e Migrações Eloquent**

### **2.1. Tabela `help_articles`**

```sql
CREATE TABLE `help_articles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(200) NOT NULL,
  `category` ENUM('aluno', 'admin', 'geral') NOT NULL DEFAULT 'geral',
  `target_page_key` VARCHAR(100) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `help_articles_slug_unique` (`slug`),
  KEY `help_articles_target_page_key_index` (`target_page_key`),
  KEY `help_articles_category_index` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### **2.2. Migration Laravel: `database/migrations/2026_01_01_000017_create_help_articles_table.php`**

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
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->enum('category', ['aluno', 'admin', 'geral'])->default('geral');
            $table->string('target_page_key', 100)->index();
            $table->longText('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
```

---

### **2.3. Model Eloquent: `app/Models/HelpArticle.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Class HelpArticle
 * 
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string $target_page_key
 * @property string $content
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class HelpArticle extends Model
{
    use HasFactory;

    protected $table = 'help_articles';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'target_page_key',
        'content',
    ];

    /**
     * Boot function to automatically generate slugs if not set.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (HelpArticle $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title);
            }
        });
    }

    /**
     * Scope for filtering by category.
     */
    public function scopeByCategory(Builder $query, ?string $category): Builder
    {
        if (empty($category) || !in_array($category, ['aluno', 'admin', 'geral'], true)) {
            return $query;
        }

        return $query->where('category', $category);
    }

    /**
     * Scope for searching by keyword in title or content.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
              ->orWhere('content', 'LIKE', "%{$term}%")
              ->orWhere('target_page_key', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Scope to locate by target page key.
     */
    public function scopeByTargetKey(Builder $query, string $key): Builder
    {
        return $query->where('target_page_key', $key);
    }
}
```

---

## **3. Architecture de Rotas, Controllers e Form Requests**

### **3.1. Definição de Rotas: `routes/web.php`**

```php
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\Admin\AdminHelpArticleController;
use Illuminate\Support\Facades\Route;

// Landing Page Pública
Route::get('/', [LandingPageController::class, 'index'])->name('public.landing');

// Central de Ajuda Pública / Autenticada
Route::prefix('ajuda')->name('help.')->group(function () {
    Route::get('/', [HelpCenterController::class, 'index'])->name('index');
    Route::get('/artigo/{slug}', [HelpCenterController::class, 'show'])->name('show');
    Route::get('/contexto/{target_page_key}', [HelpCenterController::class, 'contextual'])->name('contextual');
});

// Área Administrativa de Gestão de Artigos de Ajuda
Route::middleware(['auth', 'admin'])->prefix('admin/ajuda')->name('admin.help.')->group(function () {
    Route::get('/', [AdminHelpArticleController::class, 'index'])->name('index');
    Route::get('/criar', [AdminHelpArticleController::class, 'create'])->name('create');
    Route::post('/', [AdminHelpArticleController::class, 'store'])->name('store');
    Route::get('/{helpArticle}/editar', [AdminHelpArticleController::class, 'edit'])->name('edit');
    Route::put('/{helpArticle}', [AdminHelpArticleController::class, 'update'])->name('update');
    Route::delete('/{helpArticle}', [AdminHelpArticleController::class, 'destroy'])->name('destroy');
});
```

---

### **3.2. Form Requests**

#### **`app/Http/Requests/Admin/StoreHelpArticleRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHelpArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:help_articles,slug'],
            'category' => ['required', 'string', Rule::in(['aluno', 'admin', 'geral'])],
            'target_page_key' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título do artigo de ajuda é obrigatório.',
            'title.max' => 'O título não pode exceder 200 caracteres.',
            'slug.unique' => 'Este slug já está em uso por outro artigo.',
            'category.required' => 'A categoria é obrigatória.',
            'category.in' => 'A categoria deve ser aluno, admin ou geral.',
            'target_page_key.required' => 'A chave da página de destino (target_page_key) é obrigatória.',
            'content.required' => 'O conteúdo do artigo é obrigatório.',
        ];
    }
}
```

#### **`app/Http/Requests/Admin/UpdateHelpArticleRequest.php`**

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $articleId = $this->route('helpArticle')?->id;

        return [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:200', Rule::unique('help_articles', 'slug')->ignore($articleId)],
            'category' => ['required', 'string', Rule::in(['aluno', 'admin', 'geral'])],
            'target_page_key' => ['required', 'string', 'max:100'],
            'content' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'O título do artigo de ajuda é obrigatório.',
            'slug.required' => 'O slug é obrigatório.',
            'slug.unique' => 'Este slug já está cadastrado em outro artigo.',
            'category.required' => 'A categoria é obrigatória.',
            'target_page_key.required' => 'A chave de identificação da página é obrigatória.',
            'content.required' => 'O conteúdo do artigo não pode estar vazio.',
        ];
    }
}
```

---

### **3.3. Controllers**

#### **`app/Http/Controllers/LandingPageController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\HelpArticle;
use Illuminate\Contracts\View\View;

class LandingPageController extends Controller
{
    /**
     * Render Public Landing Page.
     */
    public function index(): View
    {
        $featuredCourses = Course::where('is_published', true)
            ->latest()
            ->take(3)
            ->get();

        $landingHelpArticle = HelpArticle::byTargetKey('public.landing')->first();

        return view('public.landing', compact('featuredCourses', 'landingHelpArticle'));
    }
}
```

#### **`app/Http/Controllers/HelpCenterController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class HelpCenterController extends Controller
{
    /**
     * Display Help Center home with search and category filtering.
     */
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = $request->query('q');

        $articles = HelpArticle::query()
            ->byCategory($category)
            ->search($search)
            ->orderBy('title')
            ->paginate(12)
            ->withQueryString();

        return view('help.index', compact('articles', 'category', 'search'));
    }

    /**
     * Show single help article by slug.
     */
    public function show(string $slug): View
    {
        $article = HelpArticle::where('slug', $slug)->firstOrFail();

        $relatedArticles = HelpArticle::where('category', $article->category)
            ->where('id', '!=', $article->id)
            ->take(4)
            ->get();

        return view('help.show', compact('article', 'relatedArticles'));
    }

    /**
     * Contextual help router by target_page_key (RN05).
     */
    public function contextual(string $target_page_key): View|RedirectResponse
    {
        $article = HelpArticle::byTargetKey($target_page_key)->first();

        if (!$article) {
            // Fallback: redirects to index with query parameter
            return redirect()->route('help.index', ['q' => $target_page_key])
                ->with('info', 'Artigo de ajuda específico para esta página ainda está em elaboração.');
        }

        return view('help.show', compact('article'));
    }
}
```

#### **`app/Http/Controllers/Admin/AdminHelpArticleController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHelpArticleRequest;
use App\Http\Requests\Admin\UpdateHelpArticleRequest;
use App\Models\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class AdminHelpArticleController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->query('q');
        $category = $request->query('category');

        $articles = HelpArticle::query()
            ->search($search)
            ->byCategory($category)
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        return view('admin.help.index', compact('articles', 'search', 'category'));
    }

    public function create(): View
    {
        return view('admin.help.create');
    }

    public function store(StoreHelpArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        HelpArticle::create($data);

        return redirect()->route('admin.help.index')
            ->with('success', 'Artigo de ajuda cadastrado com sucesso!');
    }

    public function edit(HelpArticle $helpArticle): View
    {
        return view('admin.help.edit', ['article' => $helpArticle]);
    }

    public function update(UpdateHelpArticleRequest $request, HelpArticle $helpArticle): RedirectResponse
    {
        $data = $request->validated();
        $helpArticle->update($data);

        return redirect()->route('admin.help.index')
            ->with('success', 'Artigo de ajuda atualizado com sucesso!');
    }

    public function destroy(HelpArticle $helpArticle): RedirectResponse
    {
        $helpArticle->delete();

        return redirect()->route('admin.help.index')
            ->with('success', 'Artigo de ajuda removido com sucesso!');
    }
}
```

---

## **4. Interface Visual e Componente de Ajuda Contextual**

### **4.1. Tokens de Design System Aplicados**
* **Primary (Azul Elétrico):** `#004080`
* **Accent (Amarelo Destaque):** `#F7D700`
* **Background Neutral:** `#F8FAFC`
* **Surface Neutral:** `#FFFFFF`
* **Text Primary / Secondary:** `#1E293B` / `#64748B`
* **Success / Danger:** `#10B981` / `#EF4444`

---

### **4.2. Componente Blade Reutilizável: `resources/views/components/help-button.blade.php`**

Este componente é a garantia técnica da **RN05 (Cobertura de 100% das telas)**. Deve ser incluído no cabeçalho ou subcabeçalho de todas as views Blade da aplicação.

```blade
@props([
    'key' => 'help.index',
    'tooltip' => 'Central de Ajuda Contextual',
    'label' => 'Ajuda'
])

<a href="{{ route('help.contextual', ['target_page_key' => $key]) }}"
   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#004080] bg-[#004080]/10 hover:bg-[#004080]/20 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-[#004080]"
   title="{{ $tooltip }}"
   aria-label="{{ $tooltip }}"
   target="_blank">
    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 16h-2v-2h2v2zm1.07-7.75l-.9.92C12.45 11.9 12 12.5 12 14h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z"/>
    </svg>
    <span>{{ $label }}</span>
</a>
```

---

### **4.3. Landing Page Pública: `resources/views/public/landing.blade.php`**

```blade
<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma EAD | Capacitação Técnica para Eletricistas</title>
    <meta name="description" content="Plataforma oficial de capacitação continuada para eletricistas associados. Cursos com emissão de certificado autêntico.">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#004080',
                        accent: '#F7D700',
                        neutralBg: '#F8FAFC',
                        surface: '#FFFFFF',
                        textPrimary: '#1E293B',
                        textSecondary: '#64748B',
                    }
                }
            }
        }
    </script>
</head>
<body class="font-sans antialiased text-[#1E293B] bg-[#F8FAFC] min-h-full flex flex-col justify-between">

    <!-- Header Principal -->
    <header class="bg-[#FFFFFF] border-b border-gray-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-[#004080] text-[#F7D700] flex items-center justify-center font-bold text-xl rounded-lg shadow">
                    ⚡
                </div>
                <span class="font-extrabold text-xl tracking-tight text-[#004080]">Plataforma EAD Eletricistas</span>
            </div>

            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-[#64748B]">
                <a href="#beneficios" class="hover:text-[#004080] transition-colors">Sobre a Capacitação</a>
                <a href="#cursos" class="hover:text-[#004080] transition-colors">Cursos</a>
                <a href="{{ route('help.index') }}" class="hover:text-[#004080] transition-colors">Central de Ajuda</a>
                <x-help-button key="public.landing" />
            </nav>

            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('student.courses.index') }}"
                       class="px-5 py-2.5 bg-[#004080] text-white font-semibold text-sm rounded-lg hover:bg-[#003366] transition-all shadow-md">
                        Meu Painel
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="px-5 py-2.5 bg-[#004080] text-white font-semibold text-sm rounded-lg hover:bg-[#003366] transition-all shadow-md">
                        Entrar na Plataforma
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="bg-gradient-to-br from-[#004080] to-[#002855] text-white py-16 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-block px-3 py-1 bg-[#F7D700] text-[#004080] font-bold text-xs rounded-full uppercase tracking-wider mb-4 shadow">
                    Capacitação Continuada Oficial
                </span>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-tight mb-6">
                    Aprimore suas habilidades técnicas em instalações elétricas
                </h1>
                <p class="text-lg text-blue-100 mb-8 leading-relaxed">
                    Acesse conteúdos especializados, assista a aulas em vídeo, responda a avaliações interativas e receba seu certificado com verificação pública.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="{{ route('login') }}"
                       class="px-8 py-3.5 bg-[#F7D700] text-[#004080] font-black rounded-lg hover:bg-yellow-400 transition-all transform hover:-translate-y-0.5 shadow-lg text-base">
                        Acessar Minha Conta
                    </a>
                    <a href="{{ route('help.index') }}"
                       class="px-6 py-3.5 bg-white/10 text-white font-semibold rounded-lg hover:bg-white/20 transition-all border border-white/20 text-base">
                        Conhecer a Central de Ajuda
                    </a>
                </div>
            </div>
            <div class="relative">
                <div class="w-full h-80 sm:h-96 bg-blue-900/40 rounded-2xl border border-white/10 flex items-center justify-center p-6 text-center shadow-2xl backdrop-blur-sm">
                    <div class="space-y-4">
                        <div class="w-20 h-20 mx-auto bg-[#F7D700]/20 text-[#F7D700] rounded-full flex items-center justify-center text-4xl">
                            👷‍♂️⚡
                        </div>
                        <h3 class="text-xl font-bold text-white">Eletricistas Associados</h3>
                        <p class="text-sm text-blue-200">Aulas 100% online com foco prático e suporte integral.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefícios -->
    <section id="beneficios" class="py-16 bg-[#FFFFFF]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-12">
                <h2 class="text-3xl font-extrabold text-[#004080]">Por que se capacitar conosco?</h2>
                <p class="text-[#64748B] mt-2">Plataforma desenvolvida sob medida para a evolução constante do profissional eletricista.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 bg-[#F8FAFC] border border-gray-200 rounded-xl">
                    <div class="w-12 h-12 bg-[#004080] text-white rounded-lg flex items-center justify-center text-xl mb-4">📱</div>
                    <h3 class="text-xl font-bold text-[#1E293B] mb-2">Flexibilidade Total</h3>
                    <p class="text-sm text-[#64748B]">Estude de qualquer lugar via computador ou smartphone no seu tempo livre.</p>
                </div>
                <div class="p-6 bg-[#F8FAFC] border border-gray-200 rounded-xl">
                    <div class="w-12 h-12 bg-[#004080] text-white rounded-lg flex items-center justify-center text-xl mb-4">📜</div>
                    <h3 class="text-xl font-bold text-[#1E293B] mb-2">Certificado Autêntico</h3>
                    <p class="text-sm text-[#64748B]">Emissão automática de certificado em PDF com código SHA-256 e validação pública.</p>
                </div>
                <div class="p-6 bg-[#F8FAFC] border border-gray-200 rounded-xl">
                    <div class="w-12 h-12 bg-[#004080] text-white rounded-lg flex items-center justify-center text-xl mb-4">💬</div>
                    <h3 class="text-xl font-bold text-[#1E293B] mb-2">Fórum & Suporte</h3>
                    <p class="text-sm text-[#64748B]">Tire dúvidas técnicas diretamente com instrutores e troque experiências com colegas.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-[#004080] text-white py-8 border-t border-blue-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-blue-200">
            <p>&copy; {{ date('Y') }} Plataforma EAD Eletricistas. Todos os direitos reservados.</p>
            <div class="flex items-center gap-6">
                <a href="{{ route('help.index') }}" class="hover:text-white transition-colors">Central de Ajuda</a>
                <a href="{{ route('help.contextual', ['target_page_key' => 'public.landing']) }}" class="hover:text-white transition-colors">Ajuda desta Página</a>
            </div>
        </div>
    </footer>
</body>
</html>
```

---

## **5. Estratégia de Suíte de Testes Automatizados (95%+ Coverage)**

### **5.1. Teste Unitário: `tests/Unit/Models/HelpArticleTest.php`**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\HelpArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_automatically_generates_slug_on_creation(): void
    {
        $article = HelpArticle::create([
            'title' => 'Como Emitir o Certificado',
            'category' => 'aluno',
            'target_page_key' => 'student.certificate',
            'content' => 'Passo a passo para baixar o certificado.',
        ]);

        $this->assertEquals('como-emitir-o-certificado', $article->slug);
    }

    public function test_scope_by_category_filters_correctly(): void
    {
        HelpArticle::create([
            'title' => 'Artigo Aluno',
            'slug' => 'artigo-aluno',
            'category' => 'aluno',
            'target_page_key' => 'key1',
            'content' => 'Conteúdo 1',
        ]);

        HelpArticle::create([
            'title' => 'Artigo Admin',
            'slug' => 'artigo-admin',
            'category' => 'admin',
            'target_page_key' => 'key2',
            'content' => 'Conteúdo 2',
        ]);

        $alunoArticles = HelpArticle::byCategory('aluno')->get();
        $this->assertCount(1, $alunoArticles);
        $this->assertEquals('aluno', $alunoArticles->first()->category);
    }

    public function test_scope_search_finds_matches_in_title_or_content(): void
    {
        HelpArticle::create([
            'title' => 'Dúvidas sobre Matrícula',
            'slug' => 'duvidas-matricula',
            'category' => 'aluno',
            'target_page_key' => 'key3',
            'content' => 'Instruções para ativar a conta pelo convite.',
        ]);

        $results = HelpArticle::search('convite')->get();
        $this->assertCount(1, $results);
        $this->assertEquals('duvidas-matricula', $results->first()->slug);
    }
}
```

---

### **5.2. Teste de Integração: `tests/Feature/HelpCenterControllerTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpCenterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_renders_successfully(): void
    {
        $response = $this->get(route('public.landing'));
        $response->assertStatus(200);
        $response->assertSee('Plataforma EAD Eletricistas');
    }

    public function test_help_center_index_displays_articles(): void
    {
        HelpArticle::create([
            'title' => 'Primeiros Passos',
            'slug' => 'primeiros-passos',
            'category' => 'geral',
            'target_page_key' => 'help.index',
            'content' => 'Guia inicial de utilização da plataforma.',
        ]);

        $response = $this->get(route('help.index'));
        $response->assertStatus(200);
        $response->assertSee('Primeiros Passos');
    }

    public function test_contextual_help_redirects_or_shows_article(): void
    {
        HelpArticle::create([
            'title' => 'Ajuda da Dashboard Admin',
            'slug' => 'ajuda-dashboard-admin',
            'category' => 'admin',
            'target_page_key' => 'admin.dashboard',
            'content' => 'Explicação das métricas da dashboard.',
        ]);

        $response = $this->get(route('help.contextual', ['target_page_key' => 'admin.dashboard']));
        $response->assertStatus(200);
        $response->assertSee('Ajuda da Dashboard Admin');
    }

    public function test_admin_can_create_help_article(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.help.store'), [
            'title' => 'Novo Artigo de Teste',
            'category' => 'aluno',
            'target_page_key' => 'student.test',
            'content' => 'Conteúdo detalhado de teste.',
        ]);

        $response->assertRedirect(route('admin.help.index'));
        $this->assertDatabaseHas('help_articles', [
            'title' => 'Novo Artigo de Teste',
            'target_page_key' => 'student.test',
        ]);
    }

    public function test_non_admin_cannot_access_admin_help_crud(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($student)->get(route('admin.help.index'));
        $response->assertStatus(403);
    }
}
```

---

## **6. Lista de Tarefas de Desenvolvimento (Checklist)**

- [ ] **Tabela & Migration:** Executar `php artisan make:migration create_help_articles_table` e ajustar campos.
- [ ] **Model & Seeder:** Criar `HelpArticle` com scopes `byCategory`, `search` e `byTargetKey`. Criar seeder inicial para `public.landing`, `help.index`, `admin.dashboard`.
- [ ] **Componente Blade Contextual:** Criar `<x-help-button key="..." />` e plugar no layout base da aplicação.
- [ ] **Controllers:** Implementar `LandingPageController`, `HelpCenterController` e `AdminHelpArticleController`.
- [ ] **Views Public & Help:** Construir `landing.blade.php`, `help/index.blade.php`, `help/show.blade.php` com Tailwind CSS e tokens visuais.
- [ ] **Views Admin CRUD:** Desenvolver listagem, formulário de inclusão e edição em `resources/views/admin/help/`.
- [ ] **Suíte de Testes:** Executar testes unitários e de integração validando 95%+ de cobertura.
