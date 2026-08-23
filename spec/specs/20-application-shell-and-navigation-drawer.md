# **20. Shell Global da Aplicação, Topbar, Drawer Mobile e Notificações (Material Bootstrap)**

---

## **1. Visão Geral & Mapeamento de Requisitos Multitenant**

* **Objetivo:** Implementar o Shell Master da aplicação (`layouts/app.blade.php`), Topbar de 76px (compacta de 60px no mobile), Drawer Lateral claro de 280px (gaveta deslizante de 264px com 220ms no mobile), Badge de Organização Ativa e Dropdown de Notificações.
* **Roles Cobertas:** `role:admin`, `role:gestor`, `role:aluno`.
* **Referência de Design:** `spec/new_ds/DESIGN.md` §4.1 e §4.14, `spec/new_ds/No celular - Anatomia.dc.html`.

---

## **2. Arquitetura do Shell & Layout Master**

### 2.1 Topbar Desktop & Mobile (`components/layout/topbar.blade.php`)
- **Desktop (`≥ lg`):** Altura fixa 76px (`--appbar-height`), fundo `--surface`, elevação `--elev-1`, padding horizontal 32px, `z-index: 20`.
  - **Brand Mark:** Quadrado 44px, raio 14px (`--radius-md`), fundo `--primary` (`#4c6fe7`), 2-3 letras em 16px/800 branco, nome da instituição ao lado em 18px/800 (`-0.02em` tracking).
  - **Busca em Pílula:** Altura 48px, max 420px, fundo `--surface-alt`, ícone Lucide `search` (20px), placeholder *"Buscar cursos, alunos, certificados…"*.
  - **Badge de Contexto Org:** Chip `.ds-chip.ds-chip-info` com nome da organização ativa. Em modo Impersonate: `.ds-chip-primary` + botão ghost *"Sair do contexto"* (`impersonate-org.destroy`).
  - **Ajuda Contextual:** Componente `<x-help-button>` circular de 44px com modal integrado.
  - **Sino de Notificações:** Botão ghost 48px com ícone Lucide `bell` (22px), badge numérico pílula em `--secondary` (menta `#2e9e6b`) com anel branco de 3px. Desaparece quando contador for 0.
  - **Dropdown de Notificações:** Largura 380px, elevação `--elev-4`, lista com tint `--blue-50` em itens não lidos, botão *"Marcar todas como lidas"*.
  - **Menu de Conta:** Avatar 44px (iniciais sobre menta pastel), nome (16px/700) e role em caption. Dropdown com Perfil, Trocar Senha e Sair.
- **Mobile (`< lg`):**
  - Altura reduzida para **60px**, fixa no topo com `z-index: 30`.
  - Botão Hambúrguer 44x44px (`aria-expanded="false"`, `aria-controls="mobile-drawer"`).
  - Brand mark compacta (32px) + título da tela ativa + avatar do usuário (34px).
  - Campo de busca e sino recolhidos para o menu interno.

### 2.2 Drawer Lateral Desktop & Gaveta Mobile (`components/layout/sidebar.blade.php`)
- **Desktop (`≥ lg`):** Drawer fixo claro de 280px, fundo `--surface`, borda direita `--divider`.
  - 3 seções do `NavigationRegistry`: *Painel*, *Acompanhamento*, *Aprendizado*.
  - Itens de menu: Altura 52px, raio 14px, padding 12px 16px, ícone Lucide 20px, texto 15px/600.
  - Item Ativo: Fundo azul claro `--primary-container` (`#e7ecfd`), texto `--on-primary-container` (`#3550bc`), `aria-current="page"`.
  - Contadores de Pendência: Badge menta pílula (`.ds-chip-success.ds-chip-plain`) à direita.
- **Gaveta Mobile (`< lg`):**
  - Painel deslizante de **264px** de largura (ou 82% da viewport).
  - Transição de entrada suave em **220ms** (`cubic-bezier(.2,0,0,1)`).
  - Scrim / Overlay escuro de fundo: `rgba(15, 23, 42, 0.42)`.
  - Trap de foco ativo: foco preso na gaveta enquanto aberta, fecha com toque no scrim ou tecla `Esc`.
  - Bloqueio de rolagem no `body` (`overflow: hidden`) enquanto aberta.

---

## **3. Interações AJAX & Controle de Estado**

1. **Contador de Notificações em Tempo Real:** Endpoint `GET /notifications/unread-count` consultado periodicamente e atualizando badge sem recarregar tela.
2. **Leitura de Notificação via AJAX:** `PATCH /notifications/{id}/read` e `PATCH /notifications/read-all` atualizam o dropdown e decrescem o contador instantaneamente.
3. **Alternância de Drawer Mobile:** Manipulação puramente via classes CSS (`.is-open`) e eventos de toque nativos sem dependências externas quebradas.

---

## **4. Seletores Dusk & Contrato E2E**

* `dusk="topbar-brand"`: Elemento da marca/logo no topo.
* `dusk="active-org-badge"`: Chip indicativo da organização ativa.
* `dusk="exit-impersonate"`: Botão de sair do contexto de impersonação.
* `dusk="notifications-bell"`: Botão do sino de notificações.
* `dusk="notifications-count"`: Badge com contagem de notificações não lidas.
* `dusk="user-menu-button"`: Botão de abertura do menu do usuário.
* `dusk="sidebar-nav"`: Container `<nav>` principal da navegação.
* `dusk="mobile-menu-toggle"`: Botão hambúrguer no mobile.
* `dusk="mobile-drawer"`: Container do drawer móvel.

---

## **5. Checklist de Implementação & Testes**

- [ ] Layout `resources/views/layouts/app.blade.php` refatorado no padrão Material Bootstrap.
- [ ] Parciais `topbar.blade.php`, `sidebar.blade.php`, `footer.blade.php` atualizados.
- [ ] Dropdown de notificações com suporte a AJAX integrado.
- [ ] Suporte a Mobile Drawer com animação 220ms e foco acessível.
- [ ] Teste Feature: `NavigationMenuTest.php` cobrindo RBAC de rotas por role.
- [ ] Teste Dusk: `Theme/ResponsiveShellTest.php` validando Topbar, Drawer mobile e Dropdown de notificações.
