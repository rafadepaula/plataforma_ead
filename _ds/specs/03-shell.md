# 03 — Shell (topbar, drawer, page header, footer)

**Views de origem:** `layouts/app.blade.php`, `components/layout/topbar.blade.php`,
`sidebar.blade.php`, `app/Services/Navigation/NavigationRegistry.php`

## Objetivo
Casca presente em toda tela autenticada — primeira peça a migrar (onda A,
risco médio) porque tudo depende dela.

## Layout e componentes
- **Topbar**: marca, busca, badge da organização ativa, ajuda contextual
  (`HelpButton`), sino com contador (`NotificationsBell`), usuário com papel.
  Sobre `--surface-container-lowest`.
- **Sidebar** drawer claro 280px sobre `--surface-container-lowest`, três
  seções do `NavigationRegistry` — Painel, Acompanhamento, Aprendizado. Item
  ativo em `--primary-surface` com texto `--primary`, badge de pendência em
  `--success`, alvo de item 48px.
- `main` com `--space-8` (32px) de padding sobre `--background #faf8ff`,
  conteúdo até `--container-max` (1280px).
- **PageHeader**: breadcrumb + caption + `headline-2xl` + apoio em
  `--on-surface-variant`, em toda tela.
- **Footer** com a organização.

## Estados
- Item ativo: `aria-current="page"`.
- Mobile: drawer colapsa — ver `16-mobile.md` (bug de Alpine é pré-requisito).

## Contrato `dusk`
Itens de navegação, busca, sino e avatar de usuário têm seletores fixos usados
em múltiplas telas — mudar aqui quebra 03-screen-inventory.md inteiro. Testar
com o conjunto mais amplo de specs de Dusk, não só uma tela.

## Critérios de aceite
- `NavigationRegistry` continua a única fonte da estrutura de menu.
- Drawer mobile abre e fecha (bug atual corrigido, não herdado).
- `artisan dusk` completo (não filtrado) passa antes de fechar esta fase.
