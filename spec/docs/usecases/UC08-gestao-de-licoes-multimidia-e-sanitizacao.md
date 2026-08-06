# **Especificação de Caso de Uso: UC08 — Gestão de Lições Multimídia e Sanitização**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC08
* **Nome:** Gestão de Lições Multimídia e Sanitização
* **Módulo:** Gestão Pedagógica (`Lessons & Multimedia`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF07** | Permitir ao Admin/Gestor cadastrar aulas com Texto (Rich Text), Imagem, PDF e Vídeo do YouTube sanitizado via `YouTubeSanitizerService` e `FileUploadService`. |
| **Regra de Negócio** | **RN08** | Restrição de Escopo Tenant por Curso/Módulo. |
| **Regra de Negócio** | **RN12** | Contexto Org Preservado. |
| **Regra de Negócio** | **RN14** | Sanitização contra XSS e Auditoria. |

---

## **3. Visão Geral e Objetivo**

Permitir o cadastro, edição e organização de lições/aulas associadas a um módulo. A aplicação suporta lições de conteúdo multimídia rico (Texto formatado, upload seguro de imagens e arquivos PDF protegidos) e incorporação de vídeos do YouTube com sanitização automática da URL para garantir parâmetros restritivos de navegação e privacidade.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor`.
* O módulo pai (`modules`) deve estar cadastrado.

### **4.2. Pós-condições**
* Lição gravada na tabela `lessons` com o tipo de conteúdo apropriado.
* Arquivos PDF e Imagens salvos em `storage/app/public/lessons/{id}/` através do `FileUploadService`.
* URL do YouTube formatada e sanitizada em `lessons.youtube_url`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Criação de Lição Multimídia e Sanitização de Vídeo**

1. O gestor navega até o gerenciador de lições de um módulo (`GET /modules/{module}/lessons`).
2. A tela exibe as lições existentes no módulo e o botão **"+ Nova Lição"** (`GET /modules/{module}/lessons/create`).
3. A interface `lessons.create` apresenta:
   - **Título da Lição** (`name="title"`, obrigatorio).
   - **Tipo de Lição** (`name="type"`, select: `content` ou `quiz`).
   - **Conteúdo em Texto (Rich Text)** (`name="content_text"`, editor HTML).
   - **URL do Vídeo do YouTube** (`name="youtube_url"`, input text).
   - **Arquivo PDF Complementar** (`name="pdf"`, file input `.pdf`, max 10MB).
   - **Imagem de Capa/Ilustrativa** (`name="image"`, file input `.jpg,.png,.webp`, max 5MB).
   - **Publicada?** Checkbox (`name="is_published"`).
4. O gestor preenche o título, cola uma URL comum do YouTube (ex: `https://www.youtube.com/watch?v=dQw4w9WgXcQ`), seleciona um arquivo PDF e clica em **"Salvar Lição"** (`POST /modules/{module}/lessons`).
5. O `StoreLessonRequest` valida o formulário.
6. O backend invoca o `YouTubeSanitizerService::sanitize()`:
   - Extrai o ID do vídeo da URL colada.
   - Constrói a URL sanitizada de embed padrão: `https://www.youtube.com/embed/dQw4w9WgXcQ?modestbranding=1&rel=0&controls=1&disablekb=1`.
7. O `FileUploadService` processa o upload do PDF/Imagem, armazenando no disco `public` em diretório seguro e gravando o caminho em `pdf_path` e `image_path`.
8. A lição é gravada na tabela `lessons` com a ordem `order_index` correta. O sistema redireciona para o módulo com confirmação: *"Lição criada com sucesso."*

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: URL do YouTube Inválida**
* **Gatilho:** Inserção de link de outro site (ex: Vimeo ou link quebrado) no campo `youtube_url`.
* **Comportamento:** O `YouTubeSanitizerService` rejeita o formato e o Form Request retorna erro de validação HTTP 422 sob o campo: *"Forneça uma URL válida de vídeo do YouTube."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /modules/{module}/lessons`, `POST /modules/{module}/lessons`, `GET /lessons/{lesson}/edit`, `PUT /lessons/{lesson}`, `DELETE /lessons/{lesson}`.
* **Middleware:** `auth`, `role:admin|gestor`.
* **Services:** `App\Services\YouTubeSanitizerService`, `App\Services\FileUploadService`.
