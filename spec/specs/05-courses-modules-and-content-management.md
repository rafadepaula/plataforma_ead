# **05. Gestão Multitenant de Cursos, Módulos e Conteúdos Multimídia**

---

## **1. Visão Geral & Escopo Multitenant**

* **RF06:** Permitir ao Gestor da Organização (`role:gestor`) cadastrar, listar, editar e excluir Cursos e Módulos da sua Organização (`org_id`). Reordenação de módulos via AJAX/jQuery.
* **RF07:** Cadastro de Lições multimídia (Rich Text, Imagem, PDF e Vídeo do YouTube).
* **Isolamento Multitenant:**
  - O modelo `Course` utiliza a Trait `OrgScope`.
  - Todo curso criado é automaticamente gravado com `org_id = auth()->user()->org_id`.
  - Módulos e Lições herdam o escopo do curso ao qual pertencem.
* **Roles Autorizadas:** `role:admin|gestor`.

---

## **2. Modelo do Banco de Dados**

- **`courses`**: `id`, `org_id` (FK -> `organizations.id`), `title`, `description`, `workload_hours`, `is_published`.
- **`modules`**: `id`, `course_id` (FK -> `courses.id`), `title`, `description`, `order_index`.
- **`lessons`**: `id`, `module_id` (FK -> `modules.id`), `title`, `type` (`content`, `quiz`), `content_text`, `youtube_url`, `pdf_path`, `image_path`, `order_index`, `is_published`.

---

## **3. Domain Services & Armazenamento Isolado**

- **`OrgScope`**: Aplica automaticamente `where('org_id', auth()->user()->org_id)` nos cursos.
- **`FileUploadService`**: Upload de arquivos direcionado para pastas isoladas `storage/app/public/orgs/{org_id}/courses/...`.
- **`YoutubeSanitizerService`**: Valida e extrai ID do YouTube gerando embed restrita sanitizada.

---

## **4. Checklist de Implementação & Testes (Target: 95%+ Coverage & Dusk E2E)**

- [ ] Migration `courses` com a coluna `org_id`
- [ ] Model `Course` utilizando a Trait `OrgScope`
- [ ] Harness: Criar/atualizar as 3 skills (`courses-architecture`, `courses-conventions`, `courses-maintenance`)
- [ ] Testes Automatizados Backend & Dusk E2E: `MultiTenantCourseManagementTest.php`, `ModuleReorderTest.php` aprovados com 100%.
