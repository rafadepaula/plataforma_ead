---
name: frontend-architecture
description: >
  Redireciona para `bootstrap-architecture` — descreve o sistema
  "Modernist Design System" pré-migração (zero-radius, `ModalManager.js`,
  `NotificationService.js`, badge `.tag-*`), que **não existe mais no
  código**. Use apenas para entender o histórico; para arquitetura de
  frontend atual (camadas, tokens, componentes `<x-ui.*>`), use
  `bootstrap-architecture`.
---

# Frontend Architecture (`frontend-architecture`)

> **Este skill está obsoleto.** Descrevia o frontend anterior à migração
> para Bootstrap 5.3 nativo e ao redesign Material. Nenhum dos
> padrões abaixo existe mais no código: `ModalManager.js` e
> `NotificationService.js` foram removidos na migração para Bootstrap 5.3
> nativo; `.tag-*`, `.dialog`/`.dialog-backdrop`, `.field`/`.input`, `.elev-*`
> e `.grayscale` eram classes fantasma ou foram descontinuadas (a última,
> `.grayscale`, saiu de vez na Fase 2 do redesign). O sistema de zero-radius
> foi substituído pelo Material Bootstrap de cantos suaves.
>
> **Arquitetura atual**: `bootstrap-architecture` (modelo de 5 camadas,
> camada de tokens `_ds/plataforma-ead-design-system/`, ponte `_bridge.scss`,
> mandato de componentização). Convenções de código atuais:
> `bootstrap-conventions`. Debug/build/Dusk: `bootstrap-maintenance`.
>
> Este arquivo fica só como referência histórica do que existia antes —
> não seguir nenhuma regra abaixo em código novo.

## Histórico (Modernist Design System, pré-migração — não usar)

Frontend usava camadas com micro-componentes Blade + Bootstrap 5.3 (só
grid/utilities) + Modernist Design System (zero-radius, accent vermelho),
com comportamento dinâmico em módulos JS artesanais
(`resources/js/modules/ModalManager.js`, `NotificationService.js`,
`HttpClient.js`). Ver `bootstrap-architecture` § "Decision Record" para o
mapeamento completo de cada peça antiga para o equivalente atual.
