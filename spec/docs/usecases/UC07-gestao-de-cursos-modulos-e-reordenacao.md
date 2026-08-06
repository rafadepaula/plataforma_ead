# **Especificação de Caso de Uso: UC07 — Gestão Multitenant de Cursos, Módulos e Reordenação Drag-and-Drop**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC07
* **Nome:** Gestão Multitenant de Cursos, Módulos e Reordenação Drag-and-Drop
* **Módulo:** Gestão Pedagógica de Cursos (`Courses & Modules`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF06** | Permitir ao Admin/Gestor criar, editar, reordenar por drag-and-drop AJAX e soft-deletar Cursos e Módulos com guard de matrículas ativas. |
| **Regra de Negócio** | **RN08** | Restrição de Escopo Tenant (Cursos pertencem à `org_id` ativa). |
| **Regra de Negócio** | **RN11** | **Guard de Exclusão de Cursos:** A exclusão de um Curso é bloqueada (HTTP 422) caso existam alunos com matrícula ativa (`course_user.status = 'active'`). |
| **Regra de Negócio** | **RN12** | Exceção `UnresolvedOrgContextException` para Admin sem Impersonate Org. |
| **Regra de Negócio** | **RN14** | Auditoria de Mutações (`content.deleted`, `created`, `updated`). |

---

## **3. Visão Geral e Objetivo**

Permitir a criação e estruturação de cursos e seus respectivos módulos pedagógicos no escopo da Organização ativa. O sistema permite ajustar a ordem de apresentação dos módulos visualmente via drag-and-drop com persistência assíncrona via AJAX, e protege a integridade educacional impedindo a exclusão de cursos que possuam alunos matriculados ativos (RN11).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor` com Organização ativa.

### **4.2. Pós-condições**
* Registros criados/alterados/soft-deletados nas tabelas `courses` e `modules`.
* Reordenação salva na coluna `modules.order_index`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Cadastro e Publicação de Cursos**

1. O gestor navega até o menu **"Cursos"** (`GET /courses`).
2. A tela exibe a listagem dos cursos da Organização (filtrados via `OrgScope`) com: Título, Carga Horária, Total de Módulos, Status de Publicação (`Publicado`/`Rascunho`) e Ações.
3. O gestor clica em **"+ Novo Curso"** (`GET /courses/create`).
4. O formulário solicita:
   - **Título do Curso** (`name="title"`, obrigatorio, max 200).
   - **Descrição** (`name="description"`, rich text / textarea).
   - **Carga Horária (Horas)** (`name="workload_hours"`, numero inteiro).
   - **Publicado?** Checkbox (`name="is_published"`).
5. O gestor salva (`POST /courses`). O `StoreCourseRequest` valida os dados e a Trait `OrgScope` injeta o `org_id` da Organização ativa.

---

### **5.2. Fluxo Principal 2: Adição e Reordenação Drag-and-Drop de Módulos**

1. Na lista de cursos, o gestor clica em **"Gerenciar Módulos"** do curso desejado (`GET /courses/{course}/modules`).
2. A interface exibe a árvore de módulos existentes organizados verticalmente.
3. Para criar um módulo, o gestor preenche o formulário **"Adicionar Módulo"** (`title`, `description`) e clica em **"Salvar Módulo"** (`POST /courses/{course}/modules`).
4. Para reordenar a sequência pedagógica dos módulos, o gestor arrasta uma estrutura de módulo para cima ou para baixo utilizando a alça de drag-and-drop (`.drag-handle`).
5. O script JavaScript `ModuleReorder.js` intercepta o evento de soltura (*drop*), lê a nova sequência de IDs dos módulos e envia uma requisição `POST /courses/{course}/modules/reorder` contendo JSON: `{modules: [{id: 10, order_index: 0}, {id: 8, order_index: 1}]}`.
6. O `ModuleController::reorder()` atualiza em lote a coluna `order_index` dos módulos e retorna `{success: true}` com feedback visual discreto (Toast de confirmação).

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Bloqueio de Exclusão de Curso com Alunos Ativos (RN11)**
* **Gatilho:** O gestor clica na ação **"Excluir Curso"** para um curso que possui ao menos uma linha em `course_user` com `status = 'active'`.
* **Comportamento do Sistema:**
  1. O `CourseController::destroy()` executa a verificação de segurança antes de autorizar o Soft Delete.
  2. Identifica que `$course->enrollments()->where('status', 'active')->exists() === true`.
  3. O backend aborta a exclusão e lança erro de validação HTTP 422.
  4. A interface exibe a mensagem de alerta destacada: *"Este curso não pode ser excluído pois possui alunos com matrícula ativa. Cancele as matrículas ativas antes de excluir o curso."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /courses`, `POST /courses`, `GET /courses/{course}/modules`, `POST /courses/{course}/modules`, `POST /courses/{course}/modules/reorder`, `DELETE /courses/{course}`.
* **Middleware:** `auth`, `role:admin|gestor`.
* **JS Asset:** `public/js/modules/ModuleReorder.js`.
