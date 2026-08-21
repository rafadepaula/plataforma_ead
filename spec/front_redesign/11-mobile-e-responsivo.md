# 11 — Mobile e responsivo

Breakpoints do Bootstrap 5.3, sem customização: `sm` 576 · `md` 768 · `lg` 992 ·
`xl` 1200 · `xxl` 1400. O corte de layout do shell é `lg`.

## Shell

- **App bar** cai de 76px para **64px**: botão `menu` (48px de alvo), brand
  curta (só a mark), sino. Busca vira ação que expande sobre a barra.
- **Drawer** vira `bootstrap.Offcanvas`: painel de 280px sobre scrim de 42%,
  entrando em 320ms com `--ease-emphasized`. Fecha por scrim, `Esc` e botão `x`.
  Sem Alpine — o produto não tem Alpine instalado, e diretiva de Alpine em view
  é bug, não recurso.
- `main` reduz o padding de 40px para 20px; o conteúdo vira **uma coluna**.

## Regras gerais

- **Uma coluna** abaixo de `lg`. Colunas laterais (progresso, instrutor,
  materiais, próximas aulas) descem para o fim do conteúdo, nunca somem.
- **O texto não encolhe**: base continua 16px, lead continua 19px. Reduz-se
  espaço, não legibilidade.
- Alvo de toque **≥ 48px** em todo controle; item de drawer 52px.
- Toda tabela vive dentro de `.table-responsive`; abaixo de `md`, tabelas de
  4+ colunas viram **lista de cards** com rótulo + valor por linha.
  O `dusk` migra para o mesmo nó semântico na variante card — nunca duplicado
  nas duas variantes ao mesmo tempo.
- `x-ui.fab` sobe acima da barra inferior do navegador (offset de 24px).
- Grade de cards: 1 coluna `<md`, 2 em `md`, 3 em `xl`.
- Diálogos ocupam a largura total com raio 28px só no topo em `<sm`.
- Coluna de leitura de 760px passa a 100% com padding de 20px.
- Sem scroll horizontal em nenhuma tela, em nenhum breakpoint.

## Prioridade por perfil

O Aluno é o perfil majoritariamente móvel: Meus cursos, Sala de aula, Aula e
Prova do Aluno recebem verificação de mobile **antes** das telas de gestão.

## Critérios de aceite

- Drawer abre e fecha em 375px de largura.
- Nenhuma tela produz scroll horizontal em 320 / 375 / 768 / 1024 / 1440.
- Nenhum alvo de toque abaixo de 48px.
- Dusk com `resize(375, 812)` nas quatro telas do Aluno.
