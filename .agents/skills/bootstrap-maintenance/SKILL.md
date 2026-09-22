---
name: bootstrap-maintenance
description: >
  Arquitetura, convenções e manutenção do frontend Bootstrap 5.3: modelo de 5 camadas
  (tokens SCSS, Bootstrap core, componentes, wrappers Blade, telas), wrapper de
  componente anônimo com `$attributes->merge()`, lista fechada de padrões proibidos,
  árvore utility-primeiro, gotchas de build stale `public/build/`, modal/toast/dropdown
  mortos, dompdf em `certificates/pdf.blade.php`. Use ao desenhar ou revisar tela,
  layout, componente Blade `<x-ui.*>`/`<x-layout.*>` ou módulo JS; ao escrever ou
  migrar view Blade ou parcial SCSS; ou quando Dusk falha após mudança de UI, widget
  Bootstrap não responde no browser, tela migrada fica sem estilo ou PDF de certificado
  renderiza em branco.
license: MIT
metadata:
  feature: bootstrap
  roles: [architecture, conventions, maintenance]
---

# Bootstrap 5.3 Frontend (`bootstrap-maintenance`)

Arquitetura, convenções e manutenção do frontend Bootstrap 5.3 da Plataforma EAD: modelo de 5 camadas (tokens SCSS, Bootstrap core, componentes do projeto, wrappers Blade, telas), mandato de componentização `<x-ui.*>`, princípio JS-do-Bootstrap-acima-de-JS-artesanal, e o loop de verificação por tela (build + filtro Dusk).

Conhecimento detalhado do módulo vive nos arquivos de referência abaixo —
leia apenas o que a tarefa precisa:

| Reference | Ler quando |
| --- | --- |
| `resource/architecture.md` | Desenhar ou revisar tela, layout, componente Blade ou módulo JS; antes de criar classe CSS ou componente `<x-ui.*>`; ao decidir em que camada uma regra de estilo mora. |
| `resource/conventions.md` | Escrever ou migrar view Blade, componente `<x-ui.*>`/`<x-layout.*>`, parcial SCSS ou módulo JS que dirige widget Bootstrap. |
| `resource/maintenance.md` | Dusk falha após mudança de UI; modal/toast/dropdown não responde no browser; tela migrada sem estilo ou estourando largura; PDF de certificado renderiza em branco. |
| `resource/frontend-legacy.md` | Histórico do "Modernist Design System" pré-migração (zero-radius, `ModalManager.js`, badge `.tag-*`) — ler apenas para entender padrões antigos que não existem mais no código. |
