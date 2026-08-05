# **16. Povoamento do Banco de Dados (Database Seeders) por Ambiente com Isolamento Multitenant**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **RF34:** Povoamento automatizado e modular do banco de dados (Database Seeding) adaptado estritamente por ambiente de execução (`production` vs `local`/`development`/`testing`/`staging`).
* **RF35:** Carga inicial em **Ambiente de Produção (`production`)**: população exclusiva do usuário Super Admin, garantia de existência das Roles e Permissões base do Spatie Permission (`role:admin`, `role:gestor`, `role:aluno`) e criação das configurações globais do sistema. Nenhuma massa de teste, organização fictícia ou curso de exemplo deve ser criado em produção.
* **RF36:** Carga inicial em **Ambiente de Desenvolvimento/Testes (`local`, `staging`, `testing`)**: população completa e interligada de todas as entidades necessárias para homologação e testes manuais da plataforma EAD (Organizações, Gestores, Alunos, Cursos, Módulos, Lições Multimídia, Questionários com Provas e Tentativas, Links de Convite, Certificados Emitidos, Tópicos e Respostas do Fórum, Notificações In-App e Configurações de Organização).
* **RN15:**
  - **Diferenciação Estrita de Ambiente:** O seeder principal `DatabaseSeeder` DEVE verificar programaticamente o ambiente atual via `app()->environment('production')`. Em produção, ele delega a execução exclusivamente para `RolesAndPermissionsSeeder`, `AdminSeeder` e `SystemSettingSeeder`. Em ambientes não-produção, ele executa a esteira modular completa de desenvolvimento.
  - **Credenciais Seguras & Padronizadas do Admin:** O usuário Super Admin criado via `AdminSeeder` possui e-mail configurável via `.env` (`ADMIN_EMAIL`, padrão `admin@plataforma.com`) e senha padronizada como `'admin'` (ou lida de `ADMIN_PASSWORD` / `config('app.admin_password')` se definida).
  - **Idempotência Obrigatória:** Todos os seeders DEVEM ser idempotentes. A reexecução do comando `php artisan db:seed` NUNCA deve gerar erros de chave duplicada ou duplicar registros no banco de dados. Para tal, deve-se empregar exclusivamente os métodos Eloquent `firstOrCreate` e `updateOrCreate` baseados em identificadores naturais (e-mail, slug, código, chave de configuração).
  - **Estrutura Modular no Padrão Laravel:** Cada entidade ou domínio DEVE possuir sua própria classe `Seeder` estendendo `Illuminate\Database\Seeder`, mantendo a responsabilidade única e permitindo invocações isoladas (ex.: `php artisan db:seed --class=CourseSeeder`).
  - **Supressão de Eventos & Bypass do OrgScope:** Durante o processo de seeding, os seeders DEVEM desativar disparos de eventos indesejados (como envio de e-mails via Mail/Notification e logs de auditoria pesados) usando `Model::withoutEvents()` ou `Notification::fake()`. Além disso, a passagem explícita de `org_id` nas chamadas de Model e Factory deve ser mantida para evitar a exceção `UnresolvedOrgContextException`.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.

---

## **2. Estrutura Modular das Classes Seeder & Matriz por Ambiente**

### **2.1. Arquitetura de Seeders Modulares (`database/seeders/`)**

A suíte de seeders deve ser organizada nas seguintes classes independentes dentro do diretório `database/seeders/`:

```
database/seeders/
├── DatabaseSeeder.php                # Seeder mestre e orquestrador de ambientes
├── RolesAndPermissionsSeeder.php     # Inicialização das Roles Spatie e permissões base
├── SystemSettingSeeder.php           # Configurações globais de sistema (SMTP, logo, etc.)
├── AdminSeeder.php                   # Usuário Super Admin global (Ambiente Prod e Dev)
├── OrganizationSeeder.php            # Organizações de exemplo ("Acme Cursos", "Tech Academy")
├── UserSeeder.php                    # Gestores por Org e Alunos (incluindo cenário Multi-Org RN09)
├── CourseSeeder.php                  # Cursos, Módulos e Lições multimídia (Texto, PDF, YouTube)
├── QuizSeeder.php                    # Questionários, Questões (múltipla escolha, V/F, dissertativa)
├── InvitationSeeder.php              # Links de convite inteligentes por organização (SPEC-06)
├── CertificateSeeder.php             # Certificados emitidos com hash SHA-256 e regras de liberação
├── ForumSeeder.php                   # Tópicos de discussão e respostas sanitizadas nos cursos
└── NotificationSeeder.php            # Notificações in-app (sino) para gestores e alunos
```

---

### **2.2. Matriz de Execução por Ambiente**

| Classe Seeder | `production` | `local` / `staging` / `testing` | Propósito / Conteúdo Gerado |
| :--- | :---: | :---: | :--- |
| `RolesAndPermissionsSeeder` | **SIM** | **SIM** | Cria roles `admin`, `gestor`, `aluno` via Spatie Permission |
| `SystemSettingSeeder` | **SIM** | **SIM** | Insere chaves globais padrão em `system_settings` (`org_id` = null) |
| `AdminSeeder` | **SIM** | **SIM** | Cria o usuário Super Admin (`admin@plataforma.com` / `admin`) |
| `OrganizationSeeder` | **NÃO** | **SIM** | Cria 2 Organizações: "Acme Cursos" e "Tech Academy" com CNPJ e branding |
| `UserSeeder` | **NÃO** | **SIM** | Creates 2 Gestores (1 por Org), 5 Alunos (com matrículas simples e multi-org) |
| `CourseSeeder` | **NÃO** | **SIM** | Cria 2 Cursos publicados com Módulos e Lições (texto, PDF, vídeo YouTube) |
| `QuizSeeder` | **NÃO** | **SIM** | Cria Provas com opções de resposta, gabarito e tentativas de alunos |
| `InvitationSeeder` | **NÃO** | **SIM** | Cria Links de Convite válidos e expirados para teste de cadastro adaptativo |
| `CertificateSeeder` | **NÃO** | **SIM** | Emite Certificados válidos com hash SHA-256 fixo para testes de validação pública |
| `ForumSeeder` | **NÃO** | **SIM** | Cria tópicos de dúvida nas lições e respostas entre Alunos e Gestores |
| `NotificationSeeder` | **NÃO** | **SIM** | Cria notificações in-app não lidas (sino topbar) para Aluno e Gestor |

---

## **3. Regras de Negócio & Padrões Laravel nos Seeders**

### **3.1. Orquestração no `DatabaseSeeder`**

O seeder principal `DatabaseSeeder` atua como o *dispatcher* responsável por verificar o ambiente:

```php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seeders obrigatórios para TODOS os ambientes (Prod e Dev)
        $this->call([
            RolesAndPermissionsSeeder::class,
            SystemSettingSeeder::class,
            AdminSeeder::class,
        ]);

        // 2. Trava de Produção: encerra caso o ambiente seja produção
        if (App::environment('production')) {
            $this->command->info('Ambiente de PRODUÇÃO detectado: apenas Super Admin, Roles e Settings foram populados.');
            return;
        }

        // 3. Seeders exclusivos para ambientes de Desenvolvimento / Testes
        $this->command->info('Ambiente de DESENVOLVIMENTO detectado: populando massa completa de testes...');

        $this->call([
            OrganizationSeeder::class,
            UserSeeder::class,
            CourseSeeder::class,
            QuizSeeder::class,
            InvitationSeeder::class,
            CertificateSeeder::class,
            ForumSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}
```

---

### **3.2. Garantia de Idempotência (`firstOrCreate` e `updateOrCreate`)**

Nenhum seeder deve utilizar `Model::create()` ou `DB::table()->insert()` sem verificação prévia. Exemplos dos padrões de idempotência obrigatórios:

```php
// AdminSeeder.php - Idempotência no Usuário Admin
$admin = User::firstOrCreate(
    ['email' => config('app.admin_email', 'admin@plataforma.com')],
    [
        'name' => 'Super Admin',
        'password' => Hash::make(config('app.admin_password', 'admin')),
        'status' => 'active',
        'email_verified_at' => now(),
    ]
);
$admin->assignRole('admin');

// OrganizationSeeder.php - Idempotência por Slug
$acme = Organization::firstOrCreate(
    ['slug' => 'acme-cursos'],
    [
        'name' => 'Acme Cursos',
        'cnpj' => '12.345.678/0001-90',
        'status' => 'active',
    ]
);
```

---

### **3.3. Supressão de Eventos & Isolamento Multitenant**

Para evitar disparos massivos de e-mails (como envio de convites ou notificações) e criação de logs desnecessários durante o `db:seed`, deve-se desativar os listeners de eventos:

```php
// ForumSeeder.php - Povoamento sem disparar notificações/auditoria
ForumTopic::withoutEvents(function () use ($course, $student) {
    ForumTopic::firstOrCreate(
        [
            'course_id' => $course->id,
            'title' => 'Dúvida sobre a Aula 1: Instalação',
        ],
        [
            'org_id' => $course->org_id,
            'user_id' => $student->id,
            'content' => 'Gostaria de saber qual versão do PHP usar.',
        ]
    );
});
```

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

### **4.1. Arquivos de Código a Serem Criados/Modificados**

- [ ] `database/seeders/DatabaseSeeder.php` (Orquestrador condicional por ambiente)
- [ ] `database/seeders/RolesAndPermissionsSeeder.php` (Roles Spatie `admin`, `gestor`, `aluno`)
- [ ] `database/seeders/SystemSettingSeeder.php` (Configurações globais)
- [ ] `database/seeders/AdminSeeder.php` (Usuário Admin global `admin@plataforma.com` / `admin`)
- [ ] `database/seeders/OrganizationSeeder.php` (Organizações Acme Cursos e Tech Academy)
- [ ] `database/seeders/UserSeeder.php` (Gestores por Org e Alunos com matrículas multi-org)
- [ ] `database/seeders/CourseSeeder.php` (Cursos, Módulos e Lições multimídia)
- [ ] `database/seeders/QuizSeeder.php` (Questionários, Questões e Tentativas)
- [ ] `database/seeders/InvitationSeeder.php` (Links de convite por Org)
- [ ] `database/seeders/CertificateSeeder.php` (Certificados emitidos e regras de liberação)
- [ ] `database/seeders/ForumSeeder.php` (Tópicos de discussão e respostas)
- [ ] `database/seeders/NotificationSeeder.php` (Notificações in-app)
- [ ] Model Factories atualizadas em `database/factories/` com `States` reutilizáveis

---

### **4.2. Harness Triad (Harness Skills do Agente - SPEC-03)**

- [ ] Skill Architecture: `.agents/skills/seeders-architecture/SKILL.md` (Explica a orquestração do `DatabaseSeeder`, a condicional `app()->environment('production')`, a idempotência via `firstOrCreate` e a preservação do isolamento multitenant via `org_id` explícito).
- [ ] Skill Conventions: `.agents/skills/seeders-conventions/SKILL.md` (Padrões de código para Seeders modulares, supressão de eventos `withoutEvents()`, e credenciais padronizadas do Admin).
- [ ] Skill Maintenance: `.agents/skills/seeders-maintenance/SKILL.md` (Guia de depuração de seeders, prevenção de erros de chave duplicada e testes de integridade da massa de dev/prod).

---

### **4.3. Testes Automatizados Backend (PHPUnit)**

- [ ] `tests/Feature/Seeders/DatabaseSeederProductionTest.php`:
  - Simula `App::detectEnvironment(fn () => 'production')` e executa `php artisan db:seed`.
  - Assegura que o usuário Super Admin `admin@plataforma.com` foi criado e possui a role `admin`.
  - Assegura que as roles Spatie `admin`, `gestor`, `aluno` existem no banco.
  - Assegura que NENHUMA Organização fictícia, Usuário de teste ou Curso de exemplo foi criado.
- [ ] `tests/Feature/Seeders/DatabaseSeederDevelopmentTest.php`:
  - Executa `php artisan db:seed` em ambiente `testing`/`local`.
  - Valida que Organizações, Gestores, Alunos, Cursos, Módulos, Lições, Provas, Certificados e Notificações foram devidamente criados e interligados com seus respectivos `org_id`.
- [ ] `tests/Feature/Seeders/SeederIdempotencyTest.php`:
  - Executa o comando `php artisan db:seed` DUAS vezes consecutivas no mesmo banco.
  - Assegura que a segunda execução completa sem lançar exceções de integridade/duplicidade e que o total de registros nas tabelas principais permanece idêntico (idempotência perfeita).
