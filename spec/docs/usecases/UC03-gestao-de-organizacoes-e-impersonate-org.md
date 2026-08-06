# **Especificação de Caso de Uso: UC03 — Gestão de Organizações e Impersonate Org**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC03
* **Nome:** Gestão de Organizações e Impersonate Org
* **Módulo:** Multitenancy e Gestão de Organizações (`Organizations`)
* **Atores Principais:** Administrador Global (`role:admin`)
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF23** | Permitir ao Admin Global criar, listar, editar e inativar Organizações com nome, slug, CNPJ, logo e paleta de cores. |
| **Requisito Funcional** | **RF24** | Permitir ao Admin Global selecionar uma Organização ativa em `session('active_org_id')` para operar e visualizar dados do tenant específico (Impersonate Org). |
| **Regra de Negócio** | **RN12** | Regra de Impersonate Org para Admin (Admin sem org ativa tentando criar registro escopado recebe `UnresolvedOrgContextException` HTTP 422). |
| **Regra de Negócio** | **RN14** | Mascaramento e Auditoria (`impersonate.start`, `impersonate.stop`). |

---

## **3. Visão Geral e Objetivo**

Capacitar o Administrador Global a gerenciar o ciclo de vida das Organizações (tenants) da plataforma (criação, edição de identidade visual e dados cadastrais, alteração de status) e chavear o contexto operacional do sistema para uma Organização específica através da funcionalidade **Impersonate Org**, permitindo inspecionar e gerenciar os dados daquele tenant como se estivesse atuando como um Gestor local.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O usuário deve estar autenticado com a Role `admin` (`middleware: auth, role:admin`).

### **4.2. Pós-condições**
* **Após Criar/Editar Organização:** Registro alterado na tabela `organizations` com slug gerado e arquivos de logo salvos em `storage/app/public/logos`.
* **Após Iniciar Impersonate Org:** `session('active_org_id')` preenchido com o `id` da Organização selecionada; evento `impersonate.start` auditado; bar visual superior (banner de Impersonate) exibido na interface.
* **Após Encerrar Impersonate Org:** `session('active_org_id')` removido; evento `impersonate.stop` auditado; visão global restaurada.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: CRUD de Organizações**

1. O Admin acessa o menu lateral e clica em **"Organizações"** (rota `GET /organizations`).
2. O `OrganizationController::index()` lista todas as organizações em uma tabela Blade `<x-ui.table>` contendo: Nome, Slug, CNPJ, Status (Badge `active`/`inactive`), Data de Criação e Coluna de Ações.
3. Para criar uma nova Organização, o Admin clica em **"+ Nova Organização"** (`GET /organizations/create`).
4. A tela `organizations.create` apresenta o formulário:
   - **Nome da Organização** (`name="name"`, obrigatorio, max 150).
   - **Slug** (`name="slug"`, opcional - gerado automaticamente via `Str::slug` se omitido).
   - **CNPJ** (`name="cnpj"`, apenas cadastral, mascara `00.000.000/0000-00`).
   - **Logo** (`name="logo"`, upload de imagem png/jpg/webp, max 2MB).
   - **Cor Primária** (`name="primary_color"`, seletor hex, default `#004080`).
   - **Cor Secundária** (`name="secondary_color"`, seletor hex, default `#EAB308`).
   - **Status** (`name="status"`, enum `active`/`inactive`).
5. O Admin preenche os campos e clica em **"Salvar Organização"** (`POST /organizations`).
6. O `StoreOrganizationRequest` valida os campos. O controller grava na tabela `organizations`, salva a logo em `storage/app/public/logos` e redireciona para a listagem com a mensagem: *"Organização criada com sucesso."*

---

### **5.2. Fluxo Principal 2: Iniciar e Encerrar Impersonate Org**

1. Na tabela de Organizações (`/organizations`) ou no seletor de Organização do topbar, o Admin visualiza o botão **"Operar nesta Org"** (ou ícone de olho/troca de contexto) ao lado da Organização desejada.
2. O Admin clica no botão **"Operar nesta Org"**.
3. A requisição `POST /organizations/{organization}/impersonate` é enviada (`ImpersonateOrgController::store`).
4. O controller grava `$organization->id` em `session('active_org_id')`.
5. O `AuditService` grava o evento `impersonate.start` em `audit_logs` com `admin_id` e `target_org_id`.
6. O sistema redireciona o Admin para `/admin/dashboard`. A interface exibe no topo um **Banner de Aviso Destacado** (amarelo/dourado): *"Você está operando no contexto da Organização: [Nome da Org]. [Botão: Encerrar Impersonate]"*.
7. A partir deste momento, todas as consultas Eloquent que utilizam a trait `OrgScope` filtram automaticamente os dados para a Organização selecionada.
8. Para encerrar o Impersonate, o Admin clica em **"Encerrar Impersonate"** no banner superior.
9. A requisição `DELETE /impersonate-org` é processada por `ImpersonateOrgController::destroy()`, que limpa a chave da sessão, grava o evento `impersonate.stop` em `audit_logs` e redireciona o Admin para `/organizations`.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Admin Tenta Criar Registro Escopado Sem Impersonate Ativo (RN12)**
* **Gatilho:** O Admin Global (que possui `users.org_id = null`) navega diretamente para uma tela de criação escopada (ex: criar um curso) sem ter selecionado uma Organização ativa no Impersonate Org.
* **Comportamento:**
  1. O handler do evento `creating` da Trait `OrgScope` detecta `$user->org_id === null` e `session('active_org_id') === null`.
  2. A Trait dispara a exceção `UnresolvedOrgContextException`.
  3. O `bootstrap/app.php` captura a exceção e retorna HTTP 422 com a mensagem: *"Não foi possível resolver o contexto da Organização. Selecione uma Organização ativa via Impersonate Org antes de cadastrar novos registros."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:**
  - `GET /organizations` (`OrganizationController@index`)
  - `GET /organizations/create` (`OrganizationController@create`)
  - `POST /organizations` (`OrganizationController@store`)
  - `GET /organizations/{organization}/edit` (`OrganizationController@edit`)
  - `PUT /organizations/{organization}` (`OrganizationController@update`)
  - `POST /organizations/{organization}/impersonate` (`ImpersonateOrgController@store`)
  - `DELETE /impersonate-org` (`ImpersonateOrgController@destroy`)
* **Middleware:** `auth`, `role:admin`.
* **Exceção de Contexto:** `App\Exceptions\UnresolvedOrgContextException`.
