---
name: frontend-maintenance
description: >
  Build de assets e cobertura E2E genéricos (ainda válidos), mas
  redireciona para `bootstrap-maintenance` para troubleshooting de UI —
  as seções antigas (`LayoutRenderingTest`, `BladeComponentsTest`,
  `ModalManager`, zero-radius) descreviam testes e módulos que não
  existem mais no código.
---

# Frontend Maintenance (`frontend-maintenance`)

> **Troubleshooting de UI atual (build stale, modal, dropdown, radius,
> classe fantasma) mora em `bootstrap-maintenance`.** As seções antigas
> deste arquivo citavam `LayoutRenderingTest` e `BladeComponentsTest` —
> nenhum dos dois arquivo existe em `tests/` — e um bug de `ModalManager`
> que foi removido do projeto na migração para Bootstrap 5.3 nativo. Não
> seguir essas seções; foram removidas daqui. As duas seções abaixo (build
> e cobertura E2E por cadeia) continuam corretas e não são específicas de
> Bootstrap.

---

## Build

```bash
# Build de produção de CSS e JS
vendor/bin/sail npm run build

# Desenvolvimento (Hot Module Replacement)
# ATENCAO: cria public/hot. Pare o dev server antes de rodar Dusk —
# com public/hot no lugar, todo asset e servido de localhost:5173, que
# dentro do container do Selenium resolve para ele mesmo, e a suite
# inteira roda sem CSS nem JS. Ver `laravel-dusk`.
vendor/bin/sail npm run dev
```

Ver `bootstrap-maintenance` §1 para o gotcha de `public/build/` desatualizado
quebrando Dusk silenciosamente.

---

## Testes Dusk E2E

```bash
# Todos os testes de navegador
vendor/bin/sail artisan dusk

# Filtro mais estreito possível para a tela/feature em questão
vendor/bin/sail artisan dusk --filter=<TesteDaTela>
```

Troubleshooting de UI (modal, dropdown, backdrop, `border-radius`, classe
fantasma, `public/build/` stale): `bootstrap-maintenance`.

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
