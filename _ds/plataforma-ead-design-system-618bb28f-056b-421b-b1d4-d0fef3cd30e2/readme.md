# Plataforma EAD — Design System

Linguagem visual de **Plataforma EAD Multitenant**, um LMS brasileiro (Laravel +
Blade) que serve três perfis num único banco multitenant: **Admin**
(infraestrutura global), **Gestor** (cursos, alunos e certificados de uma
Organização) e **Aluno** (assiste aulas, faz provas, baixa certificados).

Este design system foi **completamente reescrito** na direção **"Material
Bootstrap"**: azul e menta pastéis, cantos suaves, elevação tonal, tipografia
maior e espaçamento generoso. A versão anterior — o tema "Modernist" do
repositório (cinza quente, vermelho `#ec3013`, canto zero, Archivo 800) — foi
substituída por completo. O **comportamento, a informação e o vocabulário** do
produto continuam vindo do código-fonte; apenas a aparência é nova.

## Fontes

- **Repositório GitHub:** https://github.com/rafadepaula/plataforma_ead (branch `main`)
  - Tokens originais: [`resources/scss/_tokens.scss`](https://github.com/rafadepaula/plataforma_ead/blob/main/resources/scss/_tokens.scss), [`app.scss`](https://github.com/rafadepaula/plataforma_ead/blob/main/resources/scss/app.scss), [`components/_index.scss`](https://github.com/rafadepaula/plataforma_ead/blob/main/resources/scss/components/_index.scss)
  - Biblioteca de componentes: [`resources/views/components/ui/`](https://github.com/rafadepaula/plataforma_ead/tree/main/resources/views/components/ui) e [`components/layout/`](https://github.com/rafadepaula/plataforma_ead/tree/main/resources/views/components/layout)
  - Telas: [`resources/views/`](https://github.com/rafadepaula/plataforma_ead/tree/main/resources/views)
  - Navegação: `app/Services/Navigation/NavigationRegistry.php`
  - Documentação de migração: `spec/front_migration/02-component-inventory.md`, `03-screen-inventory.md`, `05-bootstrap-reference.md`

Vale explorar esses caminhos direto no repositório antes de construir telas
novas: os arquivos Blade carregam comentários longos explicando por que cada
componente se comporta como se comporta, e `spec/front_migration/` traz o
inventário completo de telas e permissões por perfil.

## Produtos representados

1. **Aplicação autenticada** (`ui_kits/lms_app/`) — app bar + drawer claro +
   conteúdo: dashboard, cursos e módulos, meus cursos, sala de aula, aula em
   vídeo, prova, fórum, redações pendentes, configurações.
2. **Superfícies públicas** (`ui_kits/public_site/`) — landing, login, convite
   de matrícula e validação pública de certificado.

Não há app mobile, site de marketing separado nem template de slides no
código-fonte, então nada disso foi inventado.

---

## CONTENT FUNDAMENTALS

**Português do Brasil, sempre.** Interface, validações, estados vazios e ajuda
contextual são todos em pt-BR. Substantivos do domínio mantêm a inicial
maiúscula quando nomeiam a entidade: Organização, Curso, Módulo, Aula, Prova,
Certificado, Matrícula, Aluno, Gestor.

- **Voz institucional e direta, em segunda pessoa.** O usuário é *você*:
  "Você atingiu o número máximo de tentativas (3).", "Continue de onde parou.",
  "Sua nota preliminar é 80%." O sistema não fala "nós".
- **Rótulos são substantivos; botões são verbos no infinitivo.** Navegação:
  "Cursos e módulos", "Redações pendentes", "Moderação do fórum", "Auditoria".
  Botões: "Novo curso", "Enviar prova", "Confirmar matrícula", "Baixar
  certificado", "Salvar alterações", "Sair do contexto".
- **Caixa de texto: sentence case em tudo.** Diferente da versão anterior,
  chips e badges **não** são mais caixa-alta — escreva "Publicado", "Em
  andamento", "Concluído". A única caixa-alta é o `overline` (13px, .08em), um
  recurso tipográfico aplicado por CSS a rótulos de seção e kickers.
- **Títulos com kicker.** Toda tela abre com um overline azul sobre o título:
  *Painel / Bom dia, Joana*, *Gestão / Cursos e módulos*, *Aprendizado / Meus
  cursos*, *Sala de aula / <curso>*, *Acesso / Entrar na plataforma*,
  *Validação pública / Certificado nº 9f2b7c41*.
- **Subtítulos explicam o propósito da tela em uma frase**, em 19px cinza-azulado:
  "Organize módulos, aulas e provas da sua organização.", "Suas matrículas em
  todas as organizações, com progresso em tempo real."
- **Estados vazios sempre têm próximo passo.** "Nenhum tópico por aqui" +
  "Abra o primeiro tópico e comece a conversa com a turma." + botão. Nunca só a
  constatação seca.
- **Estados degradados não culpam o usuário:** "Estamos preparando o conteúdo de
  ajuda desta tela.", "Vídeo indisponível — avise o responsável pelo curso."
- **Texto destrutivo é calmo e explícito:** "Esta ação não poderá ser desfeita.
  Deseja continuar?" A revogação de certificado pede motivo, com "Mínimo de 10
  caracteres."
- **Tom de urgência não existe.** Nada é "urgente", "atenção!", "erro crítico".
  Pendências são apresentadas como trabalho a fazer: "Precisa da sua atenção",
  "4 redações aguardando correção".
- **Números em pt-BR:** vírgula decimal ("+4,2%"), datas `dd/mm/aaaa`, carga
  horária como "4 horas" (ou "4h" em tabelas densas).
- **Sem emoji na interface.**

## VISUAL FOUNDATIONS

**Cor.** Três famílias pastel e nenhum tom quente. **Azul** `--blue-600`
(`#4c6fe7`) é o primário — ações, foco, navegação ativa. **Menta**
`--mint-600` (`#2e9e6b`) é o secundário: sucesso, conclusão, contadores.
**Sky** `--sky-600` (`#2c7bd1`) é informativo. Cada família tem rampa 50–900 e
um par *container / on-container* pastel (`--primary-container` `#e7ecfd` com
texto `--on-primary-container` `#3550bc`), que é o que carrega chips, alerts e
ícones. Os neutros são frios, levemente azulados (`--grey-50` `#f6f8fc` até
`--grey-900` `#1b2437`).

**Vermelho, laranja e amarelo estão proibidos no sistema.** Ação destrutiva usa
`--critical` `#3b4a78`, um azul-slate profundo, sempre acompanhado de ícone
(escudo/lixeira) e de texto explícito — a hierarquia comunica gravidade, não a
matiz. Chamados de atenção neutros usam `--attention` (cinza).

**Tipografia.** Nunito Sans, humanista e levemente arredondada, em 400 / 600 /
700 / 800. Base **16px** com entrelinha 1,6 — nada de leitura abaixo disso, e o
menor texto do sistema é 13px (caption). Escala: display 44, h1 36, h2 30,
h3 24, h4 20, h5 18, h6 16; lead 19, body-sm 15, label 14, caption 13.
Títulos são 700 com tracking -.02em; métricas de dashboard são 40px/800.

**Cantos.** Suaves em todo lugar: 6 / 10 / 14 / 20 / 28 / 36px e `pill`.
Botões, chips e paginação são pílulas completas; campos 14px; cards 20px;
diálogos 28px; heros 36px. Nenhuma aresta reta no sistema.

**Fundos.** Superfícies planas e claras, com faixas pastéis para separar
seções: corpo `#f6f8fc`, cards brancos, faixas `--blue-50`, painel de
autenticação `--blue-100`. O único gradiente permitido é o *pastel wash*
(`linear-gradient(135deg, var(--blue-100), var(--mint-100))`) usado como
placeholder de mídia em cards e no player de vídeo. Sem textura, sem padrão
repetido, sem hero fotográfico, sem glassmorphism.

**Elevação.** É o principal recurso de profundidade, substituindo as bordas da
versão anterior. Cinco níveis com sombra **azulada** (nunca preto neutro):
elev-1 cards e tabelas, elev-2 cards em destaque, elev-3 FAB e hover de card,
elev-4 dropdowns, elev-5 diálogos. O CTA primário e o FAB ganham um glow de
marca, `--elev-primary: 0 6px 18px rgba(76,111,231,.28)`.

**Bordas e divisores.** Discretos e secundários: `--divider` `#e2e7f0` em 1px
entre linhas de lista e de tabela; `--border-color` `#dce3ee` em campos e
cards `outlined`; tracejado 1,5px apenas no bloco de estado vazio. Onde antes
havia borda escura, agora há sombra.

**Espaçamento.** Grade de 4px, com ritmo generoso: 4 / 8 / 12 / 16 / 20 / 24 /
32 / 40 / 48 / 64. Card com 28px de padding interno, main com 40px, seções
separadas por 40px, campo com 52px de altura, alvo de toque mínimo de 48px.
Layout: app bar 76px, drawer 280px, conteúdo máximo 1240px, leitura 760px,
formulário 440px, dropdown 380px.

**Movimento.** Três durações — 120ms (cor e camada de estado), 200ms (hover,
elevação, chips), 320ms (diálogos, progresso, drawer) — com
`cubic-bezier(.4,0,.2,1)` padrão e `cubic-bezier(.2,0,0,1)` enfatizado.
Diálogos entram subindo 16px com leve escala; alerts fazem fade. Sem bounce,
sem spring, sem animação de entrada em conteúdo, sem efeito de scroll.

**Estados.** Botões preenchidos sobem 1px no hover e aprofundam a sombra;
voltam ao normal no press com sombra menor. A camada de estado é uma sobreposição
de `currentColor` a 6% (hover) e 12% (press) — o padrão Material. Foco é um anel
azul sólido de 3px com 2px de offset; campos ganham anel de 4px a 28% de opacidade.
Desabilitado é opacidade .45. Cards `interactive` sobem 3px e vão para elev-3.
Linhas de tabela recebem tint azul a 6% no hover.

**Transparência.** Apenas funcional: camada de estado (6/10/16%), tint de
linha não lida (`--blue-50`), scrim de diálogo a 42%. Nenhum `backdrop-filter`.

**Imagens.** O produto não traz fotografia nem ilustração, e nada foi desenhado
para preencher o vazio. Onde imagem apareceria, usa-se o *pastel wash* com um
chip de status sobreposto. `--image-treatment` é `saturate(1.02)` — quase
neutro: se o cliente trouxer fotos, elas entram em cor natural, sem o
grayscale da versão anterior.

**Cards.** Superfície branca, raio 20px, elevação 1 (2 quando `elevated`),
28px de padding, gap interno de 12px. Podem ter faixa de mídia de 168px,
overline, título h4, corpo, linha de meta ancorada embaixo acima de um divisor,
e rodapé em superfície afundada. Variante `outlined` troca sombra por borda;
`interactive` adiciona hover.

## ICONOGRAPHY

- **O conjunto é Lucide**, embutido como subconjunto SVG inline. Não há fonte de
  ícones, sprite nem PNG no repositório. As definições vinham de
  `resources/views/components/ui/icon.blade.php` (um `@switch` sobre ~30 nomes)
  e do `NavigationRegistry` (os glifos do menu) — este design system reproduz os
  mesmos paths em `components/actions/Icon.jsx`, sem CDN e sem substituição.
- **Especificação de desenho:** viewBox 24×24, `fill="none"`,
  `stroke="currentColor"`, `stroke-width="2"`, pontas e junções arredondadas.
  Tamanhos em uso nesta versão: 16 (chips e tabelas densas), 18 (botões, listas),
  20 (drawer, listas de aula, alerts), 22 (app bar), 24 (FAB, stat cards),
  28–32 (estados vazios, dentro de um círculo pastel).
- **Ícones quase sempre moram num container pastel** — círculo de 28–44px ou
  quadrado de 52px com raio 14px, usando o par container/on-container. Ícone solto
  monocromático aparece só em botões e em linhas de lista.
- **Nome desconhecido degrada** para o círculo de informação, em vez de
  desaparecer. Mantenha esse comportamento ao estender o conjunto.
- **Nomes disponíveis:** bell, user, users, search, chevron-up/down/left/right,
  check, play, lock, upload, plus, clock, x, book, book-open, award, settings,
  file-text, message-square, log-out, home, arrow-left, grip-vertical, filter,
  eye, eye-off, edit, trash, menu, help-circle, dashboard, buildings, clipboard,
  shield, info.
- **Emoji e caracteres unicode nunca são usados como ícone.**
- **Não existe logotipo** no repositório (`public/favicon.ico` é placeholder de
  0 byte), então `assets/` não contém marca e nenhuma foi desenhada. No lugar do
  logo há a *brand mark*: quadrado azul de 44px com raio 14px contendo 2–3
  letras (`mark="CR"`), ao lado do nome em Nunito Sans 800.

## SUBSTITUIÇÃO DE FONTE — AÇÃO NECESSÁRIA

O repositório declarava Archivo v19 com arquivos `woff2` de 0 byte em
`public/fonts/archivo/`. Esta reescrita adota **Nunito Sans**, carregada do
Google Fonts em `tokens/fonts.css` — uma escolha desta nova direção visual, não
uma tentativa de imitar a fonte original. Se houver uma família institucional
definida, envie os binários e a troca é de duas linhas.

---

## Index

Arquivos na raiz:

- `styles.css` — ponto de entrada único dos consumidores; apenas `@import`.
- `readme.md` — este guia.
- `SKILL.md` — invólucro Agent Skills para uso no Claude Code.
- `github.md` — associação com o repositório de origem e histórico de sync.
- `thumbnail.html` — tile da homepage.
- `tokens/` — `colors.css`, `typography.css`, `spacing.css`, `elevation.css`,
  `motion.css`, `fonts.css`, `primitives.css`.
- `components/ui.css` — camada de estilo dos componentes (Material Bootstrap).
- `guidelines/` — cards de especificação: Colors (6), Type (4), Spacing (2), Brand (5).
- `assets/` — vazio por decisão: o código-fonte não traz logo, ilustração ou foto.

### Components

30 componentes, agrupados por finalidade. O inventário cobre um-a-um os
componentes `x-ui.*` e `x-layout.*` do repositório, mais cinco adições
declaradas abaixo.

- `components/actions/` — **Button**, **Fab**, **Badge**, **Chip**,
  **Avatar**, **DeleteButton**, **Icon**
- `components/forms/` — **Input**, **Select**, **Textarea**, **Checkbox**,
  **Switch**, **FieldStack**, **FormActions**, **FilterBar**
- `components/data/` — **Card**, **StatCard**, **Table**, **DataTable**,
  **Progress**, **EmptyState**, **Pagination**, **Tabs**
- `components/feedback/` — **Alert**, **Modal**, **ConfirmModal**
- `components/layout/` — **Topbar**, **Sidebar**, **PageHeader**, **Footer**,
  **GuestPanel**
- `components/app/` — **HelpButton**, **NotificationsBell**

**Adições intencionais** (não existem no Blade original; entraram porque a
linguagem Material as exige):

- **Fab** — ação de criação principal flutuante; usada no fórum.
- **Chip** — filtro rápido de escolha, substitui selects de 2–3 opções.
- **Switch** — configuração de efeito imediato, no lugar de checkbox em telas de preferências.
- **Tabs** — pílulas segmentadas para alternar visões da mesma lista (Em andamento / Concluídos).
- **Avatar** — marca de identidade por iniciais, já que não há fotografia no produto.

Não portados, com motivo: `x-ui.pagination` é um wrapper do paginador do
Laravel, reescrito aqui como **Pagination** autônomo; `x-layout.alerts` e
`x-layout.public` são shells de servidor (flash de sessão, `@vite`, `<head>`)
sem superfície visual própria — a aparência deles vive em **Alert** e nos
shells dos kits. **DataTable** é alias de **Table**, mantido para continuidade
com a API upstream.

### UI kits

- `ui_kits/lms_app/` — aplicação autenticada, 9 telas navegáveis.
- `ui_kits/public_site/` — landing, login, convite e validação de certificado.

Cada kit tem README próprio mapeando cada tela para a view Blade de origem.
