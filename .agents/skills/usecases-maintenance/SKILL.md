---
name: usecases-maintenance
description: Use when creating a new use case, updating existing use cases in spec/docs/usecases/, or maintaining traceability matrices in spec/docs/usecases/index.md and spec/docs/full_spec.md.
---

# **Guia de Manutenção e Criação de Casos de Uso (Use Cases)**

---

## **1. Visão Geral e Arquitetura de Documentação**

A documentação de requisitos e especificação funcional da plataforma é mantida sob a pasta `spec/docs/` e organizada em três pilares fundamentais:

```
spec/docs/
├── full_spec.md             # Documento Mestre de Especificação do Sistema
└── usecases/
    ├── index.md             # Índice Mestre e Matriz de Rastreabilidade Cruzada
    ├── UC01-autenticacao-logout-e-recuperacao-de-senha.md
    ├── UC02-gestao-de-perfil-do-usuario.md
    └── ... (UC01 a UC23+)
```

### **1.1. Contexto dos Arquivos e Diretórios**

1. **`spec/docs/full_spec.md` (Documento Mestre):**
   - Especificação técnica consolidada do sistema EAD Multitenant.
   - Contém a visão de escopo, arquitetura técnica (PHP 8.5 / Laravel 13, MariaDB/MySQL), esquema de banco de dados (23 tabelas), Matriz de Requisitos Funcionais (**RF01 a RF33+**), Matriz de Requisitos Não-Funcionais (**RNF01 a RNF07**), Regras de Negócio (**RN01 a RN16+**), resumo dos Casos de Uso, Matriz de Rastreabilidade e Tokens do Design System.

2. **`spec/docs/usecases/` (Diretório de Casos de Uso Individuais):**
   - Contém arquivos Markdown individuais para cada Caso de Uso (padrão `UCxx-slug-do-nome.md`).
   - Cada arquivo descreve detalhadamente o fluxo verdadeiro da aplicação através de engenharia reversa do código-fonte (rotas, controllers, actions, middleware, form requests, blade views e componentes JS/AJAX).

3. **`spec/docs/usecases/index.md` (Índice e Rastreabilidade Mestre):**
   - Catálogo estruturado de todos os Casos de Uso categorizados por módulo.
   - Contém a **Matriz Completa de Rastreabilidade Cruzada** (RF vs RN vs UC) e a **Matriz de Cobertura de Regras de Negócio** (RN vs RF vs UC).

---

## **2. Regra Cardinal de Rastreabilidade**

> **TOLERÂNCIA ZERO PARA REQUISITOS ÓRFÃOS:**
> 1. Todo Requisito Funcional (**RF**) DEVE estar vinculado a **NO MÍNIMO UMA Regra de Negócio (RN)** e a **pelo menos um Caso de Uso (UC)**.
> 2. Toda Regra de Negócio (**RN**) DEVE estar vinculada a **no mínimo um Requisito Funcional (RF)** e enforcada por **pelo menos um Caso de Uso (UC)**.
> 3. Nenhum Caso de Uso pode ser criado sem declarar explicitamente quais RFs e RNs ele atende.

---

## **3. Estrutura Padrão Esperada para um Caso de Uso (`UCxx-*.md`)**

Todo novo arquivo em `spec/docs/usecases/` DEVE seguir rigorosamente a estrutura de 7 seções abaixo:

```markdown
# **Especificação de Caso de Uso: UCxx — [Nome do Caso de Uso]**

---

## **1. Identificação do Caso de Uso**

* **ID:** UCxx
* **Nome:** [Nome do Caso de Uso]
* **Módulo:** [Nome do Módulo]
* **Atores Principais:** [Actor 1, Actor 2]
* **Atores Secundários:** [Servidor SMTP, Engine de PDF, etc.]
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RFxx** | [Descrição do RF] |
| **Regra de Negócio** | **RNxx** | [Descrição da RN] |

---

## **3. Visão Geral e Objetivo**

[Descrição clara e objetiva do propósito funcional do caso de uso]

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* [Condição 1: ex: Usuário autenticado com Role X]
* [Condição 2: ex: Matrícula ativa em course_user]

### **4.2. Pós-condições**
* [Resultado 1: ex: Registro alterado na tabela Y]
* [Resultado 2: ex: Notificação disparada e log de auditoria gravado]

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: [Nome do Fluxo Happy Path]**

1. O usuário clica no menu X (rota `GET /rota`).
2. O sistema processa via `Controller@method` e renderiza a view `view.name`.
3. A interface apresenta o formulário com os campos:
   - **Campo 1** (`name="campo1"`, obrigatorio).
   - **Campo 2** (`name="campo2"`).
   - Botão **"Salvar"**.
4. O usuário preenche os campos e clica em **"Salvar"** (`POST /rota`).
5. O backend valida a requisição através do `FormRequestClass`.
6. O `ServiceClass::action()` processa os dados e salva na tabela `tabela`.
7. O sistema exibe mensagem de sucesso e redireciona para `/destino`.

### **5.2. Fluxo Alternativo: [Nome do Fluxo Alternativo]**

1. [Passos do fluxo alternativo]

---

## **6. Fluxos de Exceção e Tratamento de Erros**

### **6.1. Fluxo de Exceção 1: [Nome da Exceção / Erro]**
* **Gatilho:** [Ação que dispara o erro, ex: Formato inválido ou permissão negada]
* **Comportamento do Sistema:**
  1. O backend rejeita a requisição (HTTP 422 / 403 / 404).
  2. A interface exibe a mensagem de erro: *"[Mensagem exata]"*.

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /rota`, `POST /rota` (`Controller@action`).
* **Middleware:** `auth`, `role:admin|gestor`, `student.enrolled`.
* **Controllers & Services:** `App\Http\Controllers\...`, `App\Services\...`.
* **JS & Blade:** `public/js/modules/...js`, `resources/views/...blade.php`.
```

---

## **4. Passo a Passo para Criar ou Atualizar um Caso de Uso**

Quando um novo requisito/funcionalidade for adicionado ou alterado no sistema, siga a sequência obrigatória de 4 passos:

### **Passo 1: Fazer a Engenharia Reversa do Código-Fonte**
Inspecione as rotas (`routes/web.php`), Controllers (`app/Http/Controllers/`), Actions (`app/Actions/`), Services (`app/Services/`), Form Requests (`app/Http/Requests/`), Middlewares (`app/Http/Middleware/`), Views Blade (`resources/views/`) e scripts JS (`public/js/modules/`) para mapear o caminho real do usuário.

### **Passo 2: Criar o Arquivo Individual do Caso de Uso**
Crie o arquivo `spec/docs/usecases/UCxx-nome-do-uc.md` utilizando o template da Seção 3 acima.
- Preencha todos os passos do usuário (cliques, rotas, botões, campos e payloads).
- Garanta que todos os RFs e RNs envolvidos estejam declarados na tabela da Seção 2.

### **Passo 3: Atualizar o Índice Mestre (`spec/docs/usecases/index.md`)**
Edite `spec/docs/usecases/index.md` adicionando:
1. O novo Caso de Uso no **Catálogo de Casos de Uso por Módulo** (com link relativo `file:///...`).
2. A atualização da **Matriz Completa de Rastreabilidade Cruzada (RF vs RN vs UC)**.
3. A atualização da **Matriz de Cobertura por Regra de Negócio (RN)**.

### **Passo 4: Atualizar o Documento Mestre (`spec/docs/full_spec.md`)**
Edite `spec/docs/full_spec.md` refletindo as alterações nas seguintes seções:
1. **Seção 3 (Matriz de Requisitos Funcionais - RF):** Incluir novos RFs com suas RNs e UCs vinculadas.
2. **Seção 5 (Regras de Negócio - RN):** Incluir novas RNs com seus RFs e UCs vinculados.
3. **Seção 6 (Mapeamento de Casos de Uso):** Adicionar o novo UC e o link Markdown correspondente.
4. **Seção 7 (Matriz de Rastreabilidade):** Atualizar a matriz de rastreabilidade.

---

## **5. Checklist de Validação da Manutenção**

Antes de finalizar qualquer edição em `spec/docs/`, confirme:
- [ ] O novo arquivo foi salvo em `spec/docs/usecases/UCxx-slug.md`?
- [ ] O arquivo contém as 7 seções obrigatórias?
- [ ] Todos os RFs do UC possuem ao menos uma RN associada?
- [ ] Os fluxos principal e de exceção descrevem o caminho exato do usuário e o comportamento real do backend/JS?
- [ ] `spec/docs/usecases/index.md` foi atualizado com o novo UC e as matrizes de rastreabilidade?
- [ ] `spec/docs/full_spec.md` foi atualizado nas seções 3, 5, 6 e 7?
