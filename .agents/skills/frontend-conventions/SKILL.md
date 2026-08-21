---
name: frontend-conventions
description: >
  Redireciona para `bootstrap-conventions` — descreve regras do "Modernist
  Design System" pré-migração (zero-radius, `.grayscale`, badge `.tag-*`,
  `ModalManager`) que **não existem mais no código**. Use apenas para
  entender o histórico; para convenções de código atuais (componentes
  `<x-ui.*>`, lista de classes proibidas, tabela de tradução), use
  `bootstrap-conventions`.
---

# Frontend Conventions (`frontend-conventions`)

> **Este skill está obsoleto**, pelo mesmo motivo de `frontend-architecture`.
> Nenhuma regra abaixo se aplica a código novo. Use `bootstrap-conventions`
> para: padrão de componente Blade anônimo (`@props` + `$attributes->merge()`),
> lista fechada de classes proibidas, tabela de tradução sistema-antigo→
> Bootstrap, variantes atuais de `<x-ui.button>`/`<x-ui.badge>`/`<x-ui.alert>`
> (que já não usam vermelho/laranja/amarelo — ver `--critical`/`--attention`
> nos tokens do redesign).
>
> Pontos que mudaram e onde este arquivo historicamente errava, para quem
> vier procurar por engano:
> - `border-radius: 0px` mandatório → **substituído** por cantos suaves
>   (`$border-radius: 14px`, botões pílula).
> - `.grayscale` em toda foto → classe **removida do projeto inteiro** na
>   Fase 2 do redesign; faixas de mídia usam `.ds-pastel-wash`.
> - `<x-ui.badge variant="accent-2">` → não resolve mais para vermelho
>   (`text-bg-danger`); passa por `.ds-tone-critical` (par
>   `--critical-container`/`--on-critical-container`).
> - `<x-ui.modal>` sobre `.dialog`/`.dialog-backdrop` + `ModalManager` →
>   `.modal`/`.modal-backdrop` nativos do `bootstrap.Modal`.
> - `window.ModalManager`/`window.NotificationService` artesanais →
>   `bootstrap.Modal`/`bootstrap.Toast` (a fachada
>   `NotificationService.success()/error()` sobrevive só como wrapper fino
>   sobre `bootstrap.Toast`, não reimplementa o widget).

Regras completas e atuais: `bootstrap-conventions`.
