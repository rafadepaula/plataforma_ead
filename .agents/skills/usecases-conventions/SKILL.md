---
name: usecases-conventions
description: >
  Concrete patterns, snippets, and guardrails for creating or updating use-case
  documents in spec/docs/usecases/ and maintaining the traceability matrices in
  spec/docs/usecases/index.md and spec/docs/full_spec.md. Use whenever writing
  a new UCxx file, linking a new RF or RN to an existing UC, or updating the
  master traceability matrix after a feature change.
license: MIT
metadata:
  feature: usecases
  role: conventions
  specs:
    - spec/docs/full_spec.md
    - spec/docs/usecases/index.md
---

# Use Cases Conventions

## 1. File Naming & Location

- Files live exclusively in `spec/docs/usecases/`.
- Naming pattern: `UCxx-kebab-case-name.md` (lowercase, Portuguese slugs, no
  accents in the filename itself).
- The `xx` index is **always** zero-padded to two digits; next available index
  is determined by `ls spec/docs/usecases/ | grep -E '^UC[0-9]+' | sort | tail -1`.

```bash
# Find the next UC number
ls spec/docs/usecases/ | grep -E '^UC[0-9]+' | sort | tail -1
# e.g. UC23-menu... → next is UC24
```

## 2. Mandatory 7-Section Template

Paste this skeleton and fill every section — no section may be omitted:

```markdown
# **Especificação de Caso de Uso: UCxx — [Nome do Caso de Uso]**

---

## **1. Identificação do Caso de Uso**

* **ID:** UCxx
* **Nome:** [Nome do Caso de Uso]
* **Módulo:** [Nome do Módulo]
* **Atores Principais:** [Ator 1, Ator 2]
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
* [Condição 1]

### **4.2. Pós-condições**
* [Resultado 1]

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: [Nome do Fluxo Happy Path]**

1. O usuário clica no menu X (rota `GET /rota`).
2. O sistema processa via `Controller@method` e renderiza a view `view.name`.
3. ...

### **5.2. Fluxo Alternativo: [Nome do Fluxo Alternativo]**

1. [Passos do fluxo alternativo]

---

## **6. Fluxos de Exceção e Tratamento de Erros**

### **6.1. Fluxo de Exceção 1: [Nome da Exceção]**
* **Gatilho:** [Ação que dispara o erro]
* **Comportamento do Sistema:**
  1. O backend rejeita a requisição (HTTP 422 / 403 / 404).
  2. A interface exibe a mensagem de erro: *"[Mensagem exata]"*.

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /rota`, `POST /rota` (`Controller@action`).
* **Middleware:** `auth`, `role:admin|gestor`.
* **Controllers & Services:** `App\Http\Controllers\...`, `App\Services\...`.
* **JS & Blade:** `public/js/modules/...js`, `resources/views/...blade.php`.
```

## 3. Codebase Reverse-Engineering Checklist

Before writing section 5 (flows), inspect all of the following:

| Source | Location |
| :--- | :--- |
| Routes | `routes/web.php` |
| Controllers | `app/Http/Controllers/` |
| Actions | `app/Actions/` |
| Services | `app/Services/` |
| Form Requests | `app/Http/Requests/` |
| Middleware | `app/Http/Middleware/` |
| Blade Views | `resources/views/` |
| JS Modules | `public/js/modules/` |

Reference **exact** route URIs, controller method names, service class names,
Blade view paths, and JS module filenames in sections 5 and 7.

## 4. Updating `spec/docs/usecases/index.md`

After creating a UC file, update `index.md` in three places:

### 4.1 — Catalog entry (by module)

```markdown
| UC23 | [Menu de Navegação Dinâmico e Controle de Acesso](./UC23-menu-de-navegacao-dinamico-e-controle-de-acesso.md) | RF37, RF38, RF39 | RN40, RN41 |
```

### 4.2 — Cross-Traceability Matrix (RF vs RN vs UC)

Add or update the row for each RF that the new UC satisfies, appending `UCxx`
to the UC column:

```markdown
| RF37 | RN40 | UC23 |
```

### 4.3 — Business Rule Coverage Matrix (RN vs RF vs UC)

Add or update the row for each RN the UC enforces:

```markdown
| RN40 | RF37 | UC23 |
```

## 5. Updating `spec/docs/full_spec.md`

After updating `index.md`, sync `full_spec.md` in four sections:

| Section | What to update |
| :--- | :--- |
| **Section 3 (RF Matrix)** | Add new RFs; link to their RNs and UCs |
| **Section 5 (RN)** | Add new RNs; link to their RFs and UCs |
| **Section 6 (UC Mapping)** | Add the new UC entry with markdown link |
| **Section 7 (Traceability)** | Update the cross-reference matrix row |

## 6. Guardrails

- **Never skip sections** in the UC template — even if a section has no
  alternative flow, write "N/A" with a brief explanation.
- **Never invent route URIs or class names** — always verify against the actual
  codebase before writing section 5 or 7.
- **Never create a UC** without declaring at least one RF and one RN in section 2.
- **Always update `index.md` and `full_spec.md`** in the same commit as the
  new/updated UC file — partial traceability is worse than none.
- **UC IDs are permanent** — once assigned, a UC ID must never be reused, even
  if the UC is deprecated. Mark deprecated UCs with a `[DEPRECATED]` note at
  the top of the file instead.

## 7. Validation Checklist

Before finalising any use-case edit, confirm every item:

- [ ] File saved as `spec/docs/usecases/UCxx-slug.md`?
- [ ] All 7 sections present and non-empty?
- [ ] Every RF in section 2 has at least one RN?
- [ ] Flows reference exact route, controller, service, view, and JS names?
- [ ] `spec/docs/usecases/index.md` updated (catalog + both matrices)?
- [ ] `spec/docs/full_spec.md` updated (sections 3, 5, 6, 7)?
