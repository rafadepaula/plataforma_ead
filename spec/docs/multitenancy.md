# **Estudo Técnico, Comparativo de Pacotes e Arquitetura Multitenant (Plataforma EAD)**

Este documento registra a análise técnica do projeto de referência `conectaconselho/painel`, a avaliação do pacote oficial **`spatie/laravel-multitenancy` (v4)** e consolida todas as **decisões de design alinhadas na entrevista `/grill-me`** para a implementação da funcionalidade de **Multitenancy (Organizações / `org`)** e **Controle de Acesso por Papéis (Roles)** na **Plataforma EAD**.

---

## 1. **Análise de Abordagens de Multitenancy no Ecossistema Laravel**

### 1.1. **Abordagem do Projeto de Referência (`conectaconselho/painel`)**
- **Dependências (`composer.json`)**: O projeto de referência utiliza `spatie/laravel-permission` para RBAC, mas **não utiliza** o pacote `spatie/laravel-multitenancy`.
- **Arquitetura de Isolamento**: Implementou uma estratégia **Single-Database** enxuta e customizada utilizando a coluna `council_id` nas tabelas de domínio e o Trait Eloquent **`CouncilScope`** (Global Scope que injeta a restrição `where('council_id', auth()->user()->council_id)` automaticamente).

---

### 1.2. **Análise do Pacote Oficial `spatie/laravel-multitenancy` (v4)**
O pacote [spatie/laravel-multitenancy (v4)](https://spatie.be/docs/laravel-multitenancy/v4/introduction) é a solução oficial da Spatie para gerenciar múltiplos inquilinos em Laravel.

#### **Recursos Principais do `spatie/laravel-multitenancy`**:
1. **Model `Tenant`**: Tabela centralizada para armazenar as informações dos inquilinos (ex: `domain`, `database`, `name`).
2. **Tenant Finder**: Suporta resolução de tenant por **Domain/Subdomain** (`DomainTenantFinder`), **Path/Slug** ou **Header HTTP**.
3. **Tasks & Switching**: Pipeline de tarefas executadas automaticamente ao alternar o tenant atual (`MakeTenantCurrentTask`), como definir conexão de banco, isolar cache, alterar prefixo de storage ou ajustar configurações da aplicação em tempo de execução.
4. **Modos de Banco de Dados**:
   - **Multi-Database**: Cada tenant possui um banco de dados relacional separado.
   - **Single-Database**: Todos os tenants compartilham o mesmo banco de dados relacional utilizando Traits de escopo (`BelongsToTenant`).

---

### 1.3. **Comparativo: `spatie/laravel-multitenancy` vs. Custom `OrgScope` (`conectaconselho/painel`)**

| Critério | `spatie/laravel-multitenancy` (v4) | Custom `OrgScope` (Abordagem Aprovada) |
| :--- | :--- | :--- |
| **Resolução de Tenant** | Principalmente por Subdomínio/Domínio ou Header HTTP | **Sessão do Usuário Autenticado (`org_id`)** |
| **Complexidade em Hospedagem Compartilhada** | Requer permissão para múltiplos bancos de dados ou Wildcard DNS no cPanel | **100% compatível com 1 único banco MySQL em hospedagem compartilhada** |
| **Troca de Contexto do Admin** | Exige invocar `Tenant::makeCurrent($tenant)` | **`session(['active_org_id' => $orgId])` com Impersonate Org simples** |
| **Aluno Multi-Org** | Complexo (o pacote assume que um usuário pertence a um único tenant por subdomínio) | **Nativo**: O Aluno enxerga todos os cursos no painel "Meus Cursos" e a sala de aula resolve a Org pelo `course_id` |

> [!NOTE]
> **Decisão de Arquitetura**: Como nossa plataforma exige que **um mesmo Aluno possa se matricular em cursos de diferentes Organizações** e o projeto será hospedado em **hospedagem compartilhada de baixo custo (Single Database)**, mantivemos a arquitetura Single-DB orientada a `org_id` + Trait `OrgScope` inspirada no `conectaconselho/painel`, que resolve o escopo diretamente pela sessão do usuário e pelo curso acessado.

---

## 2. **Resumo das Decisões de Design (Sessão /Grill-Me)**

| Ramo de Decisão | Escolha de Arquitetura Aprovada |
| :--- | :--- |
| **1. Identificação da Org** | Resolução por sessão do usuário autenticado e escopo automático pelo `org_id` do usuário logado na requisição. |
| **2. Aluno Multi-Org** | O Aluno visualiza todos os seus cursos no painel "Meus Cursos" independente da Org. Ao acessar a sala de aula, o contexto da Org é resolvido dinamicamente pelo `course_id` (`courses.org_id`). |
| **3. Roles & Autorização** | Utilização de Spatie (`spatie/laravel-permission`) focada em **Roles** (`admin`, `gestor`, `aluno`), autorizando endpoints via verificação de papéis (`role:admin`, `role:gestor`, `role:aluno`). |
| **4. Visão do Admin Global** | O Admin (Super Admin) possui `org_id = null`. Para visualizar e alterar dados de uma Organização específica, o Admin utiliza o recurso de **Impersonate Org** (assumindo o contexto da Org ativa na sessão). |
| **5. Convites e Certificados** | Convites Inteligentes são vinculados ao curso/Org. A **Validação Pública (`/validar-certificado`)** é uma rota global por Hash SHA-256 que renderiza os dados e identidade visual da Org emissora. |

---

## 3. **Especificação dos Papéis (`RolesEnum`)**

- **`admin` (Super Admin / Admin do Sistema)**:
  - `org_id = null`.
  - Gestão de Organizações (`organizations`), Gestores de Organizações, Configurações Globais do Sistema e Auditoria.
  - Pode assumir o contexto de qualquer Organização via "Impersonate Org".
- **`gestor` (Gestor de Organização)**:
  - `org_id = X` (obrigatoriamente vinculado a uma Organização).
  - Gerencia Cursos, Módulos, Lições, Provas, Alunos, Convites, Fórum e Certificados **restritos à sua `org_id`**.
- **`aluno` (Aluno Capacitando)**:
  - Pode possuir matrículas ativas em cursos de múltiplas Organizações.
  - Acesso ao painel "Meus Cursos", Sala de Aula, Provas, Fórum do Curso e Emissão de Certificados.

---

## 4. **Arquitetura do `OrgScope` (Eloquent Global Scope)**

```php
namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait OrgScope
{
    protected static function bootOrgScope(): void
    {
        static::addGlobalScope('org', function (Builder $builder): void {
            $user = Auth::user();

            if (! $user) {
                // Visitantes em rotas públicas (ex: validação de certificado / convite)
                return;
            }

            if ($user->hasRole('admin')) {
                // Admin com Impersonate Org ativo na sessão
                $activeOrgId = session('active_org_id');
                if ($activeOrgId) {
                    $builder->where($builder->getModel()->getTable() . '.org_id', $activeOrgId);
                }
                return; // Se não houver active_org_id, Admin enxerga nível global
            }

            if ($user->org_id) {
                // Gestor ou usuário pertencente à Org
                $builder->where($builder->getModel()->getTable() . '.org_id', $user->org_id);
            } else {
                // Fallback de segurança
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (! auth()->check()) {
                return;
            }

            $user = auth()->user();

            if ($model->org_id) {
                return;
            }

            $resolvedOrgId = $user->org_id ?? session('active_org_id');

            if (! $resolvedOrgId) {
                throw new \App\Exceptions\UnresolvedOrgContextException(
                    "Não foi possível resolver org_id para criar ".static::class." (usuário #{$user->id} sem org_id e sem active_org_id em sessão)."
                );
            }

            $model->org_id = $resolvedOrgId;
        });
    }
}
```

> **Nota de sincronização:** este trecho deve permanecer idêntico ao definido em `spec/specs/00-architecture-database-and-guardrails.md` §3. Corrigido para lançar `UnresolvedOrgContextException` (HTTP 422) em vez de gravar `org_id = null` silenciosamente — ver nota de auditoria na SPEC-00.

---

## 5. **Tabelas do Banco de Dados com Suporte a Multitenancy**

1. **`organizations`** (Tabela Mestre dos Tenants):
   - `id`, `name`, `slug`, `cnpj`, `logo_path`, `status` (`active`, `inactive`), `timestamps`.
2. **Tabelas Escopadas (`org_id` FK -> `organizations.id`):**
   - `users` (`org_id` nullable)
   - `courses` (`org_id` not null)
   - `invitation_links` (`org_id` not null)
   - `forum_topics` (`org_id` not null)
   - `help_articles` (`org_id` nullable - artigos globais ou específicos por Org)
   - `system_settings` (`org_id` nullable)
3. **Tabelas Relacionadas por Cascata (Herda Org via FK):**
   - `modules` -> `courses.org_id`
   - `lessons` -> `modules` -> `courses.org_id`
   - `quizzes` -> `lessons`
   - `certificates` -> `user_id`, `course_id` (com `org_id` do curso)
   - `course_user` -> pivô de matrícula (permite aluno em múltiplas orgs)
   - `notifications` -> herda org indiretamente via `notifiable` (usuário)

> Esquema completo de colunas/tipos/índices/`onDelete` vive em `spec/specs/00-architecture-database-and-guardrails.md` §2.1 — este documento cobre apenas a decisão de arquitetura multitenant, não repete tipos de coluna.

---

## 6. Skills Agenticas de Manutenção (SPEC-03)

Por exigência da `spec/specs/03-agentic-harness-and-self-updating-skills.md` (mínimo de 3 skills por feature), este módulo de multitenancy mantém as seguintes skills em `.agents/skills/`, que **devem** ser consultadas antes de qualquer alteração em `OrgScope`, `RolesEnum`, migrations/models org-scoped ou no fluxo de Impersonate Org, e **sobrescritas** logo após:

- **`.agents/skills/tenancy-architecture/SKILL.md`** — visão geral do desenho multitenant, tabelas diretamente org-scoped vs. herdadas por cascata, papéis (`RolesEnum`) e Impersonate Org.
- **`.agents/skills/tenancy-conventions/SKILL.md`** — padrões de código: como aplicar o trait `OrgScope` a um Model, convenção de migration para `org_id`/`onDelete`, tratamento global de `UnresolvedOrgContextException`, e convenções de factories/testes.
- **`.agents/skills/tenancy-maintenance/SKILL.md`** — guia de debug (vazamento de dados entre Orgs, `org_id` ausente/errado), testes obrigatórios (`OrgScopeUnresolvedContextTest`, `OrgScopeTenantIsolationTest`, `OrgScopeImpersonateOrgTest`, `RolesMiddlewareTest`, `RolesEnumTest`) e edge cases conhecidos (FK `RESTRICT` de `users.org_id`, ausência de FK real em colunas pseudo-polimórficas, PK composta nullable de `system_settings`).

O agente `.agents/agents/code-reviewer.md` trata essas 3 skills como checagem obrigatória sempre que a diff em revisão for org-scoped.

> **Nota de sincronização (Bucket A pendente):** este documento e o trait `OrgScope` acima foram escritos contra o contrato de schema definido em `spec/specs/00-architecture-database-and-guardrails.md` §2/§3. Quando a Bucket A (migrations, Models Eloquent, trait `OrgScope` e `RolesEnum` de fato implementados em código) for mesclada, este documento e as 3 skills acima devem ser revisados/atualizados para refletir qualquer divergência entre o schema planejado e o schema efetivamente implementado, conforme o protocolo de auto-update da SPEC-03.
