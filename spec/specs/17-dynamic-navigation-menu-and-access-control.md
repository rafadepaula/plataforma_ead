# **17. Menu de Navegação Dinâmico e Controle de Acesso por Role com Isolamento Multitenant**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

Esta especificação define a revisão e a rearquitetura completa do sistema de menu de navegação (Sidebar Desktop, Mobile Drawer e Topbar) da **Plataforma EAD**. O objetivo principal é garantir que a navegação reflita com 100% de exatidão o controle de acesso baseado em papéis (`role:admin`, `role:gestor`, `role:aluno`), respeitando o isolamento multitenant (`org_id`), ocultando estritamente qualquer link ou funcionalidade para a qual o usuário logado não possua permissão de acesso e corrigindo todas as rotas atualmente quebradas ou apontando para links mortos (`#`).

* **RF35 — Menu Dinâmico Filtrado por Permissão e Papel:** O menu lateral (sidebar) e superior (topbar) devem renderizar apenas os itens que o usuário autenticado tem permissão explícita para acessar. Links inacessíveis nunca devem aparecer no HTML final.
* **RF36 — Resolução Correta e Dinâmica de Rotas:** Todos os links do menu devem ser gerados através de nomes de rotas válidos registrados no sistema (`route()`), eliminando referências a nomes inexistentes (como `admin.students.index` ou `admin.courses.index`) ou URLs estáticas `#`.
* **RF37 — Destaque de Item Ativo por Padrão de Sub-rotas:** A navegação deve identificar a página atual e suas sub-páginas filhas (ex: cadastro, edição, reordenação de módulos/aulas) mantendo o item pai correspondente destacado com a classe e estilização `active`.
* **RF38 — Suporte a Badges Contadores Dinâmicos:** Itens de menu que possuem tarefas pendentes (ex: redações aguardando correção manual e denúncias de fórum para moderação) devem exibir badges numéricos atualizados em tempo real para os perfis autorizados.
* **RF39 — Resolução Contextual de Rotas do Aluno:** Links que exigem contexto de curso (como o Fórum) devem resolver dinamicamente o último curso acessado pelo Aluno ou direcionar para um seletor de cursos inscritos caso não haja contexto ativo.

---

### **Regras de Negócio (`RNxx`)**

* **RN38 — Invisibilidade Total para Recursos Não Autorizados:** Se um usuário com perfil `role:aluno` estiver autenticado, os blocos e links administrativos (Dashboard, Usuários, Cursos e Módulos, Auditoria, Moderação, Configurações) **não podem ser renderizados** no HTML.
* **RN39 — Segregação de Links entre Admin e Gestor:**
  - O link de **Organizações** (`organizations.index`) deve aparecer exclusivamente para usuários com o papel `role:admin`. Usuários exclusivamente `role:gestor` não devem visualizar este item.
  - O link de **Auditoria** deve resolver para `admin.audit-logs.index` se o usuário possuir o papel `admin`, ou `gestor.audit-logs.index` se for um usuário exclusivamente `gestor`.
* **RN40 — Consistência de Acesso Direto (Paridade Rota/Menu):** O menu reflete exatamente o middleware de segurança das rotas. Se uma rota retornar HTTP 403 Forbidden para uma role, essa rota deve obrigatoriamente estar oculta no menu para essa role.
* **RN41 — Manutenção do Isolamento Tenant no Impersonate:** Quando um Admin estiver em modo Impersonate Org (`session('active_org_id')`), o menu deve manter seu perfil Admin global, porém as contagens de badges e parâmetros de rotas devem respeitar a organização impersonada.

---

## **2. Modelo do Banco de Dados & Arquitetura de Software**

Nenhum novo modelo de banco de dados é necessário. A funcionalidade utiliza a infraestrutura existente de `roles` e `permissions` do Spatie Permission, juntamente com Eloquent Models existentes (`QuizAttempt`, `ForumReport`, `Organization`, `User`).

---

### **Arquitetura de Navegação (`NavigationService` & `NavigationComposer`)**

A estrutura do menu deixa de ser codificada de forma rígida em arquivos Blade e passa a ser gerenciada de forma centralizada pela camada de serviço e `ViewComposer`:

1. **`App\Services\Navigation\NavigationRegistry`**:
   Configuração declarativa de todos os itens e seções do menu. Cada item especifica:
   - `key`: Identificador único do item.
   - `label`: Nome exibido na interface.
   - `route`: Nome da rota primária (`route(...)`).
   - `active_patterns`: Array de padrões de nome de rota para ativação (`['users.*']`, `['courses.*', 'modules.*', 'lessons.*']`).
   - `icon`: SVG/Ícone correspondente do Modernist Design System.
   - `roles`: Roles permitidas (ex: `['admin', 'gestor']`).
   - `permissions`: Permissões específicas exigidas (opcional).
   - `badge_callback`: Closure/Método responsável por retornar o número pendente (opcional).
   - `section`: Seção do menu (`Administração`, `Aprendizado`, `Sistema`).

2. **`App\Services\Navigation\NavigationService`**:
   Serviço responsável por receber o usuário logado, filtrar os itens do `NavigationRegistry` verificando `$user->hasRole(...)` e `$user->can(...)`, resolver os nomes de rotas e compilar a estrutura tratada de seções e itens.

3. **`App\Http\View\Composers\NavigationComposer`**:
   `ViewComposer` registrado para as views `components.layout.sidebar` e `components.layout.topbar`, injetando as variáveis `$navigationSections` e `$topbarMenuItems` prontas para renderização.

---

## **3. Domain Services & Regras de Negócio**

### **Mapeamento Oficial de Itens de Menu por Perfil**

#### **Seção: Administração**
| Item | Rota Primária | Padrões Ativos (`active_patterns`) | Visibilidade de Roles |
|---|---|---|---|
| **Dashboard** | `admin.dashboard` | `['admin.dashboard']` | `admin`, `gestor` |
| **Organizações** | `organizations.index` | `['organizations.*']` | `admin` (exclusivo) |
| **Alunos & Usuários** | `users.index` | `['users.*']` | `admin`, `gestor` |
| **Cursos e Módulos** | `courses.index` | `['courses.*', 'modules.*', 'lessons.*', 'quizzes.*']` | `admin`, `gestor` |
| **Redações Pendentes** | `quiz-attempts.pending` | `['quiz-attempts.*']` | `admin`, `gestor` (Badge: total em `awaiting_manual_grading`) |
| **Moderação do Fórum** | `forum-moderation.index` | `['forum-moderation.*']` | `admin`, `gestor` (Badge: total de denúncias pendentes) |
| **Auditoria** | `admin.audit-logs.index` (ou `gestor.audit-logs.index`) | `['admin.audit-logs.*', 'gestor.audit-logs.*']` | `admin`, `gestor` |
| **Configurações** | `settings.edit` | `['settings.*']` | `admin`, `gestor` |

#### **Seção: Aprendizado**
| Item | Rota Primária | Padrões Ativos (`active_patterns`) | Visibilidade de Roles |
|---|---|---|---|
| **Meus Cursos** | `student.courses.index` | `['student.courses.*', 'classroom.*']` | `aluno`, `gestor`, `admin` |
| **Fórum de Dúvidas** | `forum.index` (contextual ou seletor) | `['forum.*', 'forum-replies.*']` | `aluno` (com matrícula em curso) |

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] **Classe de Configuração/Registro:** `App\Services\Navigation\NavigationRegistry` com declaração de todas as seções e itens.
- [ ] **Domain Service:** `App\Services\Navigation\NavigationService` implementando a filtragem por `auth()->user()`.
- [ ] **View Composer:** `App\Http\View\Composers\NavigationComposer` vinculado no `AppServiceProvider` para injetar `$navigationSections`.
- [ ] **Refatoração dos Componentes Blade:**
  - `resources/views/components/layout/sidebar.blade.php`: substituição do código imperativo pelo loop limpo `@foreach($navigationSections as $section)`.
  - `resources/views/components/layout/topbar.blade.php`: correção do link de início e ações do usuário.
- [ ] **Harness Triad (Skills):**
  - `navigation-architecture`
  - `navigation-conventions`
  - `navigation-maintenance`
- [ ] **Suíte de Testes PHPUnit (Backend Unit & Feature):**
  - `tests/Unit/NavigationServiceTest.php`: valida filtragem de itens para Admin, Gestor e Aluno.
  - `tests/Feature/NavigationComposerTest.php`: valida injeção de dados na view da sidebar e topbar.
  - `tests/Feature/RoleMenuVisibilityTest.php`: garante que nenhum link restrito vaze para perfis não autorizados no HTML renderizado.
- [ ] **Suíte de Testes Dusk E2E (Browser):**
  - `tests/Browser/NavigationMenuDuskTest.php`: navegação E2E clicando em cada link do menu como Admin, Gestor e Aluno, verificando o status ativo do link e a ausência de links desautorizados.
