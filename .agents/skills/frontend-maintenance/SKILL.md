---
name: frontend-maintenance
description: Manutenção do frontend: build de assets, testes Dusk E2E, troubleshooting.
---

# Frontend Maintenance (`frontend-maintenance`)

Procedimento de manutenção, debug e verificação automatizada da camada de frontend.

---

## Build

```bash
# Build de produção de CSS e JS
vendor/bin/sail npm run build

# Desenvolvimento (Hot Module Replacement)
vendor/bin/sail npm run dev
```

---

## Testes Dusk E2E

Suíte de interface garante layout e componentes Blade.

```bash
# Todos os testes de navegador
vendor/bin/sail artisan dusk

# Renderização de layout
vendor/bin/sail artisan dusk --filter=LayoutRenderingTest

# Componentes Blade UI
vendor/bin/sail artisan dusk --filter=BladeComponentsTest
```

---

## Troubleshooting

### 1. Dusk falha por `border-radius`
- **Sintoma**: `LayoutRenderingTest` acusa `Expected border-radius to enforce 0px`.
- **Causa**: regra CSS com radius > 0, ou Reboot do Bootstrap importado.
- **Solução**: `app.css` com `--radius-sm: 0px`, `--radius-md: 0px`, `--radius-lg: 0px`. Import do Bootstrap restrito a `grid` e `utilities`.

### 2. AJAX retorna 419 (CSRF token mismatch)
- **Sintoma**: chamada via `HttpClient` dá HTTP 419.
- **Causa**: falta `<meta name="csrf-token" content="{{ csrf_token() }}">` no `<head>` de `app.blade.php`.
- **Solução**: adicionar a meta tag no layout master.

### 3. Modal não fecha com `Escape` nem clique no backdrop
- **Sintoma**: clique fora não esconde modal.
- **Causa**: falta `.dialog-backdrop` envolvendo `.dialog`, ou listener não vinculado no `ModalManager`.
- **Solução**: conferir init de `ModalManager` em `app.js` e estrutura HTML do backdrop.

---

## Cobertura E2E por Cadeia de Ciclo de Vida

Testes em `tests/Browser/` agrupam por **jornada do usuário** — um método cobre criar, editar, transicionar, excluir, consequência. Não agrupa por módulo, tela ou spec.

- **Achar cobertura**: buscar pelo seletor, não pelo nome do arquivo:
  ```bash
  grep -rn 'dusk="meu-seletor"' tests/Browser/
  ```
  Não existir arquivo por tela/módulo não é lacuna.
- **Adicionar cobertura**: estender a cadeia existente com etapa numerada (`// N.`) que asserta UI **e** banco. Método novo só para negativa independente (403, cross-tenant, outro ator). Arquivo novo só para jornada nova.
- **Custo**: cada método paga truncate + boot do WebDriver + login + navegação. Nunca quebrar cadeia em métodos atômicos por ação. Nunca usar `pause()`/`sleep()` como espera.
- **Banco**: nenhuma trait de banco em `tests/Browser/*` — `DatabaseTruncation` vem de `Tests\DuskTestCase`. Arquivo em `storage/app/public`, cache e sessão **não** são limpos entre métodos: cadeia com upload usa nome único.

Regra completa: `testing-conventions`. Debug de cadeia: `testing-maintenance`.
