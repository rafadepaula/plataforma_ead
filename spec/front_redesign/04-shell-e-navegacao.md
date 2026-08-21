# 04 — Shell e navegação

**Views:** `layouts/app.blade.php`, `layouts/guest.blade.php`,
`components/layout/topbar.blade.php` (5 dusk), `sidebar.blade.php` (2 dusk),
`page-header.blade.php`, `footer.blade.php`, `alerts.blade.php`,
`components/notifications-bell.blade.php` (7 dusk),
`components/help-button.blade.php` (6 dusk).
**Fonte de menu:** `app/Services/Navigation/NavigationRegistry.php`.

Primeira peça a migrar: toda tela autenticada depende dela.

## `layouts/app.blade.php`

Estrutura atual (`app-wrapper` > topbar + `app-body` > sidebar + `main` +
footer) está correta e **fica**. Muda o que cada peça vale:

- `main` com padding de 40px (`--main-pad`) sobre `--surface-body`, conteúdo
  limitado a 1240px (`--content-max`) e centralizado.
- `<x-layout.alerts />` permanece imediatamente antes do slot.
- Remover as classes cruas `p-4` / `bg-body` do `main` em favor da classe de
  componente `.app-main`, que consome os tokens.

## Topbar — 76px

Da esquerda para a direita:
1. **Brand mark** — quadrado azul de 44px, raio 14px, 2–3 letras + nome em 800.
2. **Busca** em pílula, largura máxima 420px, ícone `search` 18px à esquerda.
3. **Badge da organização ativa** — chip `--primary-container`; quando há
   Impersonate Org, o chip ganha ação "Sair do contexto".
4. **`x-help-button`** — ícone `help-circle` 22px, abre modal contextual.
5. **`x-notifications-bell`** — ícone `bell` 22px + contador menta; dropdown 380px.
6. **Usuário** — `x-ui.avatar` 32px + nome + papel em caption; dropdown com
   Perfil e Sair.

Em mobile a app bar cai para 64px: botão `menu`, brand curta, sino. Ver
`11-mobile-e-responsivo.md`.

## Sidebar — drawer claro de 280px

- Fundo `--nav-bg` (branco), divisor à direita, **sem** sombra em desktop.
- Seções nomeadas em overline, vindas do `NavigationRegistry` — Painel,
  Acompanhamento, Aprendizado, Administração. O registry continua a **única**
  fonte da estrutura; nenhum item hardcoded na view.
- Item de 52px: ícone 20px + rótulo 16px. Ativo em pílula `--nav-active-bg` com
  texto `--nav-fg-active` e `aria-current="page"`.
- Badge de pendência (redações, denúncias) em menta, alinhado à direita.
- Item bloqueado por permissão não aparece — não existe item desabilitado.

## PageHeader

Presente em **toda** tela, sem exceção:

```
breadcrumb (caption, separador chevron-right 16px)
overline azul  ← o kicker
h1             ← o título
lead 19px      ← uma frase explicando o propósito da tela
[ações à direita: no máximo 1 primary + N tonais]
```

Pares kicker/título canônicos: *Painel / Bom dia, {nome}*,
*Gestão / Cursos e módulos*, *Aprendizado / Meus cursos*,
*Sala de aula / {curso}*, *Acesso / Entrar na plataforma*,
*Validação pública / Certificado nº {hash}*.

## Footer

Organização ativa + versão. Conteúdo inalterado; só recebe `--surface`,
divisor superior e caption 13px.

## `layouts/guest.blade.php`

Shell público: `x-layout.guest-panel` (46%, `--blue-100`, brand mark + frase
institucional) + coluna de conteúdo de 440px (`--form-max`) centrada.
Usado por login, recuperação de senha, redefinição e convite.

## Contrato `dusk`

Os 5 seletores da topbar, 2 do sidebar, 7 do sino e 6 do botão de ajuda são
consultados por **múltiplas** suítes (`NavigationMenuDuskTest`,
`NotificationBellTest`, `HelpCenterDuskTest`, `ImpersonateOrgTest`, além de
toda tela que faz login). Quebrar aqui derruba a suíte inteira.

## Critérios de aceite

- `NavigationRegistry` continua a única fonte da estrutura de menu.
- Drawer mobile abre e fecha (via `bootstrap.Offcanvas`, sem Alpine).
- `aria-current="page"` no item ativo.
- **Suíte Dusk completa (não filtrada)** verde antes de fechar esta fase.
