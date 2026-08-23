# DESIGN.md — Plataforma EAD Multitenant

Especificação da linguagem visual do refactor de front-end e das telas
principais. A aparência é nova; o comportamento, a informação e o vocabulário
continuam vindo do código-fonte (`rafadepaula/plataforma_ead`, branch `main`).

Referência visual navegável: `Novo Design System - Telas.dc.html` (22 slides,
1920×1080).

Direção: **Material Bootstrap** — azul e menta pastéis, cantos suaves, elevação
tonal, tipografia de 16px, espaçamento generoso. Substitui integralmente o tema
"Modernist" (cinza quente, vermelho `#ec3013`, raio 0, Archivo 800).

---

## 1. Por que refatorar

Medições do inventário de migração (`spec/front_migration/01-diagnostico`):

| Fato | Consequência |
|---|---|
| 634 estilos inline em 78 views | Nenhuma utility ou token vence o inline; a migração precisa **deletar** |
| Arquivos `.woff2` de Archivo com 0 byte (C11) | A tipografia declarada nunca funcionou em nenhum ambiente |
| Diretivas Alpine sem Alpine instalado (C10, C13) | Drawer mobile nunca abre; dismiss de alert é inerte |
| Paginação sem CSS em 9 telas (C12) | Tema Tailwind do paginador carregado sem folha de estilo |
| Modal, toast e dropdown reimplementados em JS artesanal (C9) | ~14 KB deletáveis, comportamento divergente entre telas |

O problema não é gosto: é ausência de uma fonte única de verdade de design.

---

## 2. Tokens

### 2.1 Cor

Três famílias pastel, rampa 50–900 cada. **Vermelho, laranja e amarelo estão
proibidos no sistema.**

| Papel | Token | Valor |
|---|---|---|
| Primário | `--primary` = `--blue-600` | `#4c6fe7` |
| Primário hover / pressed | `--primary-hover` / `--primary-pressed` | `#3d5bd0` / `#3550bc` |
| Secundário (conclusão, sucesso) | `--secondary` = `--mint-600` | `#2e9e6b` |
| Terciário (informação) | `--tertiary` = `--sky-600` | `#2c7bd1` |
| Destrutivo / bloqueado | `--critical` | `#3b4a78` (azul-slate) |
| Atenção neutra | `--attention` = `--grey-600` | `#5b6880` |

Pares container / on-container carregam chips, alerts e ícones:
`--primary-container #e7ecfd` / `--on-primary-container #3550bc`;
`--secondary-container #e4f5ec` / `--on-secondary-container #1e6a4a`;
`--info-container #e2f0fd` / `--on-info-container #1f5289`;
`--critical-container #e8ecf6` / `--on-critical-container #2c3760`.

Superfícies e texto: corpo `--surface-body #f6f8fc`, card `--surface #ffffff`,
afundada `--surface-sunken #fbfcfe`, alternativa `--surface-alt #eef1f7`;
texto `--text-primary #1b2437`, `--text-secondary #5b6880`,
`--text-disabled #8e99af`; `--divider #e2e7f0`, `--border-color #dce3ee`.

Ação destrutiva **nunca** comunica gravidade pela matiz: usa `--critical` +
ícone (escudo ou lixeira) + texto explícito.

### 2.2 Tipografia

**Nunito Sans** (Google Fonts), pesos 400 / 600 / 700 / 800. Base 16px,
entrelinha 1,6. Nada abaixo de 13px.

| Estilo | Tamanho | Peso |
|---|---|---|
| display | 44px | 700 |
| h1 | 36px | 700 |
| h2 | 30px | 700 |
| h3 | 24px | 700 |
| h4 | 20px | 700 |
| h5 / h6 | 18 / 16px | 700 |
| lead | 19px | 400 |
| body | 16px | 400 |
| body-sm | 15px | 400 |
| label (botões) | 14px | 700 |
| caption / overline | 13px | 400 / 700 |
| métrica | 40px | 800 |

Títulos com `letter-spacing: -.02em`. `overline` é o único uso de caixa-alta
(`.08em` de tracking). Ação necessária: se houver família institucional
definida, envie os binários — a troca é de duas linhas em `tokens/fonts.css`.

### 2.3 Espaço

Grade de 4px: 4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64 · 80.
Padding de card 28px, de célula 18×20px, de main 40px; seções separadas por
40px. Layout: app bar 76px, drawer 280px, conteúdo máx. 1240px, leitura 760px,
formulário 440px, dropdown 380px. Campo 52px, controle 48px, alvo de toque
mínimo 48px.

### 2.4 Canto e elevação

Raios: 6 / 10 / 14 / 20 / 28 / 36 / pill. Botões e chips são pílulas; campos
14px; cards 20px; diálogos 28px; heros 36px. Nenhuma aresta reta.

Elevação substitui borda como recurso de profundidade — cinco níveis de sombra
**azulada** (`rgba(27,36,55,…)`, nunca preto neutro): 1 cards e tabelas,
2 cards em destaque, 3 FAB e hover de card, 4 dropdowns, 5 diálogos.
`--elev-primary: 0 6px 18px rgba(76,111,231,.28)` só no CTA primário e no FAB.

### 2.5 Movimento

120ms cor e camada de estado · 200ms hover, elevação, chips · 320ms diálogos,
progresso, drawer. `cubic-bezier(.4,0,.2,1)` padrão,
`cubic-bezier(.2,0,0,1)` enfatizado. Sem bounce, sem spring, sem animação de
entrada em conteúdo, sem efeito de scroll.

### 2.6 Imagem

O produto não traz fotografia nem ilustração, e nada foi desenhado para
preencher o vazio. Onde imagem apareceria usa-se o *pastel wash*
`linear-gradient(135deg, var(--blue-100), var(--mint-100))` com um chip de
status sobreposto. Não existe logotipo no repositório: no lugar dele, a *brand
mark* (quadrado azul de 44px, raio 14px, 2–3 letras) ao lado do nome em 800.

### 2.7 Ícones

Subconjunto **Lucide** inline: viewBox 24×24, `fill="none"`,
`stroke="currentColor"`, `stroke-width="2"`, pontas arredondadas. Tamanhos 16
(chips, tabelas densas), 18 (botões, listas), 20 (drawer, alerts), 22 (app bar),
24 (FAB, stat cards), 28–32 (estados vazios). O conjunto é exatamente o de
`resources/views/components/ui/icon.blade.php` + os glifos do
`NavigationRegistry`. Nome desconhecido degrada para `info`. Emoji nunca é
ícone.

---

## 3. Componentes — mapeamento DE ↔ PARA

30 componentes. Cada `x-ui.*` / `x-layout.*` do repositório tem substituto
direto.

| Repositório (Blade) | Novo componente | Observação |
|---|---|---|
| `x-ui.button` | **Button** | variantes primary / tonal / secondary (outlined) / ghost / success / danger; `sm` 40px, base 48px, `lg` 60px |
| `x-ui.badge` | **Badge** | chip pílula, sentence case; `accent` → primary, `accent-2` → critical |
| `x-ui.icon` | **Icon** | mesmos paths Lucide |
| `x-ui.card` | **Card** | `elevated` / `outlined` / `interactive`, faixa de mídia 168px, meta ancorada |
| `x-ui.stat-card` | **StatCard** | ícone em container pastel 52px, métrica 40px/800, delta em chip |
| `x-ui.table` / `x-ui.data-table` | **Table** / **DataTable** (alias) | toolbar opcional, hover com tint azul a 6% |
| `x-ui.pagination` | **Pagination** | reescrito autônomo — resolve C12 |
| `x-ui.empty-state` | **EmptyState** | ícone 32px em círculo pastel, borda tracejada 1,5px, sempre com próximo passo |
| `x-ui.progress` | **Progress** | 10px, pílula, variante menta para conclusão |
| `x-ui.alert` | **Alert** | tonal: primary / success / info / attention / danger |
| `x-ui.modal` | **Modal** | raio 28px, elev-5, entra subindo 16px, scrim 42% |
| `x-ui.confirm-modal` | **ConfirmModal** | texto calmo e explícito, botão `--critical` |
| `x-ui.delete-button` | **DeleteButton** | abre confirmação; nunca remove em um clique |
| `x-ui.input` / `x-ui.textarea` / `x-ui.select` | **Input** / **Textarea** / **Select** | outlined, rótulo flutuante, 52px, anel de foco de 4px |
| `x-ui.checkbox` | **Checkbox** | 22px, alvo de 48px |
| `x-ui.field-stack` / `x-ui.form-actions` / `x-ui.filter-bar` | **FieldStack** / **FormActions** / **FilterBar** | — |
| `x-layout.topbar` | **Topbar** | 76px, marca + busca em pílula + badge da org ativa + ajuda + sino + usuário |
| `x-layout.sidebar` | **Sidebar** | drawer **claro** de 280px, seções nomeadas, item ativo em pílula `--nav-active-bg`, badge de pendência em menta |
| `x-layout.page-header` | **PageHeader** | breadcrumb + overline azul + h1 + lead |
| `x-layout.footer` | **Footer** | — |
| `x-layout.public` | **GuestPanel** + shell do kit público | painel de marca em `--blue-100` |
| `x-help-button` | **HelpButton** | fallback: "Estamos preparando o conteúdo de ajuda desta tela." |
| `x-notifications-bell` | **NotificationsBell** | dropdown 380px, item não lido com tint `--blue-50` |
| `x-layout.alerts` | (sem superfície própria) | shell de flash de sessão; aparência vive em **Alert** |
| `certificates/pdf.blade.php` | **exceção** | dompdf-safe, não recebe a camada nova |

**Adições intencionais** (não existem no Blade atual, a linguagem Material as
exige): **Fab** (criar tópico no fórum), **Chip** (filtro rápido, substitui
select de 2–3 opções), **Switch** (preferência de efeito imediato),
**Tabs** (pílulas segmentadas: Em andamento / Concluídos), **Avatar** (iniciais,
já que não há fotografia).

---

## 4. Telas — especificação

Slides 8–21 do arquivo de apresentação. Perfis cobertos nesta rodada: **Gestor**
e **Aluno**.

### 4.1 Shell — `layouts/app.blade.php`
App bar de 76px (marca, busca em pílula de até 420px, badge da organização
ativa, ajuda contextual, sino com contador, usuário com papel). Drawer claro de
280px com três seções do `NavigationRegistry` — Painel, Acompanhamento,
Aprendizado — item ativo em pílula azul, badge de pendência em menta, item de
52px. `main` com 40px de padding sobre `--surface-body`, conteúdo até 1240px.
Rodapé com a organização.

### 4.2 Dashboard — `dashboard/index.blade.php`
Kicker "Painel" + saudação. Filtro de período em chips (7 dias / 30 dias /
este ano). Quatro `StatCard`: alunos ativos, certificados emitidos, taxa de
conclusão, cursos publicados — delta em chip menta, número em pt-BR ("+4,2%").
Grade 2fr/1fr: tabela "Matrículas recentes" (aluno com avatar e e-mail, curso,
progresso em barra de 8px, status em chip) + coluna com "Precisa da sua
atenção" (redações, denúncias, certificados prontos) e "Cursos mais
concluídos" em barras menta. Ações: Exportar CSV (tonal), Novo curso (primary).

### 4.3 Cursos e módulos — `courses/index.blade.php`
`FilterBar` com busca e status. `DataTable`: curso (título + "2 módulos ·
13 aulas" em caption), carga, alunos, status em chip, ações agrupadas
(Abrir tonal / Editar ghost / Remover em `--critical`). Paginação estilizada.
Remover sempre passa por `ConfirmModal`; curso com matrícula ativa é protegido.

### 4.4 Módulos e lições — `courses/modules/_list.blade.php`, `modules/lessons/_form.blade.php`
Lista arrastável mantendo o contrato `data-reorder-url` e o `ModuleReorder.js`;
alça `grip-vertical` de Lucide no lugar do caractere `⠿`; linha em
`--surface-sunken` com elev-1 e raio 14px. Formulário de lição: título, tipo de
conteúdo, URL do YouTube com pré-visualização no pastel wash, uploads de imagem
e PDF, "Publicado" como **Switch**. Hint preservado: o servidor revalida o link
no envio.

### 4.5 Autoria de prova — `quizzes/edit.blade.php`, `partials/_question-form`
Coluna esquerda: regras (título, instruções, máximo de tentativas, limite de
tempo, nota mínima, permitir novas tentativas, exibir gabarito) com os hints
atuais palavra por palavra. Coluna direita: lista de questões arrastável com
tipo em chip outline, e o diálogo de questão (enunciado, tipo entre os quatro
valores — única escolha, múltipla escolha, verdadeiro ou falso, dissertativa —
e opções com checkbox de correta, adicionar e remover linha).

### 4.6 Correção de dissertativas — `quizzes/attempts/pending.blade.php`, `attempts/show.blade.php`
Fila com aluno, prova e data `dd/mm/aaaa hh:mm` + ação Corrigir. Tela de
correção: resposta do aluno em superfície afundada com texto preservado,
veredito em par de rádios Correta / Incorreta, e as objetivas já corrigidas
listadas com chip de resultado. Toda dissertativa da tentativa precisa de
veredito antes de salvar.

### 4.7 Meus cursos — `student/courses/index.blade.php`
`Tabs` (Em andamento com contador / Concluídos / Todos). Card por matrícula:
faixa pastel com chip de status, organização no overline, descrição, progresso
com rótulo, ação que muda com o estado (Continuar / Começar / Baixar
certificado) e meta com aulas, carga e prazo. Estado vazio: "Quando sua
organização matricular você em um curso, ele aparece nesta lista."

### 4.8 Sala de aula — `classroom/show.blade.php`
Breadcrumb + kicker "Sala de aula". Um card por módulo; cada aula é uma linha
com ícone em círculo de 44px — menta com check quando concluída, azul com
play / file-text / clipboard quando pendente — título, meta ("Vídeo · 14 min"),
chip "Prova" quando aplicável e chevron. Coluna lateral: progresso
("8 de 13 aulas concluídas"), alerta informativo sobre o certificado,
instrutor com avatar de 64px.

### 4.9 Aula em vídeo — `classroom/lesson.blade.php`, `partials/_video`
Player em 16:9 sobre pastel wash. Barra "Tempo assistido" separada do progresso
do curso, com o texto atual: salvo a cada 5 segundos, concluída após 90%.
"Marcar como concluída" em menta; depois vira chip "Aula concluída". Materiais
da aula e próximas aulas na coluna lateral. Vídeo indisponível não culpa o
aluno.

### 4.10 Prova do aluno — `student/quizzes/show.blade.php`
Coluna de leitura de 760px. Cronômetro em chip info na app bar. Alerta "Antes
de começar" com as instruções e a tentativa atual. Questão a questão: overline
"Questão 1 de 3", tipo em chip outline, enunciado em h4, opções como alvos
grandes (`--surface-sunken`, borda 1,5px, rádio ou checkbox de 22px),
dissertativa em textarea com "Mínimo de 200 caracteres." Envio único; retorno
em alerta menta com nota preliminar.

### 4.11 Fórum — `forum/index.blade.php`, `partials/_topic`
Card interativo por tópico: avatar por iniciais, badge "Fixado", título em h4,
trecho, autor e tempo relativo, contagem de respostas com ícone. FAB "Novo
tópico" e diálogo com título e conteúdo. Estado vazio: "Nenhum tópico por aqui"
+ "Abra o primeiro tópico e comece a conversa com a turma."

### 4.12 Landing — `landing/show.blade.php`
Headline e proposta de valor preservados. Hero em `--blue-50` (raio 36px) com
badge, display 44px, lead, dois CTAs e três provas em números. A prova visual
do produto são os próprios componentes (card de curso, certificado emitido,
pergunta do fórum), não fotografia. Seção "Como funciona" em quatro cards e
faixa de números em `--blue-50`.

### 4.13 Login e convite — `auth/login.blade.php`, `layouts/guest.blade.php`, `convite/show.blade.php`
Login: painel de marca em `--blue-100` (46%) + coluna de 440px com e-mail,
senha, manter conectado, esqueci minha senha e CTA em bloco. Convite: nome,
e-mail (com o aviso de vínculo a conta existente), CPF, consentimento em Switch
e "Confirmar matrícula", ao lado do resumo do curso.

### 4.14 Mobile
Drawer como painel de 280px sobre scrim de 42%, entrando em 320ms — hoje ele
nunca abre (C10, C13). App bar reduzida a 64px com menu, marca curta e sino.
Conteúdo em uma coluna, sem reduzir o tamanho do texto. Alvo de toque ≥ 48px.

### 4.15 Não desenhado nesta rodada
Organizações e usuários (admin), auditoria, moderação do fórum, certificados do
gestor, validação pública de certificado, configurações e perfil. Nada foi
inventado para elas — entram numa próxima rodada. (A validação pública e
configurações existem recriadas nos UI kits do design system.)

---

## 5. Conteúdo e tom de voz

- **Português do Brasil em tudo**: interface, validações, estados vazios, ajuda.
- Substantivos do domínio com inicial maiúscula quando nomeiam a entidade:
  Organização, Curso, Módulo, Aula, Prova, Certificado, Matrícula, Aluno,
  Gestor.
- **Segunda pessoa, voz institucional e direta**: "Você atingiu o número máximo
  de tentativas (3).", "Continue de onde parou." O sistema não fala "nós".
- **Rótulos são substantivos; botões são verbos no infinitivo**: "Redações
  pendentes"; "Enviar prova", "Salvar alterações", "Confirmar matrícula".
- **Sentence case em tudo**, inclusive chips e badges: "Publicado", "Em
  andamento". Caixa-alta só no `overline`.
- **Toda tela abre com kicker + título**: *Painel / Bom dia, Joana*,
  *Gestão / Cursos e módulos*, *Sala de aula / <curso>*.
- **Subtítulo explica o propósito em uma frase**, 19px cinza-azulado.
- **Estado vazio sempre tem próximo passo**; estado degradado não culpa o
  usuário; texto destrutivo é calmo e explícito ("Esta ação não poderá ser
  desfeita. Deseja continuar?").
- **Tom de urgência não existe.** Pendência é trabalho a fazer: "Precisa da sua
  atenção", "4 redações aguardando correção".
- **Números em pt-BR**: vírgula decimal, datas `dd/mm/aaaa`, "4 horas" (ou "4h"
  em tabelas densas).
- **Sem emoji na interface.**

---

## 6. Acessibilidade e estados

- **Foco**: anel sólido de 3px `--focus-ring` com offset de 2px; campos ganham
  anel de 4px a 28% de opacidade. Nunca `outline: none` sem substituto.
- **Camada de estado** (padrão Material): `currentColor` a 6% no hover, 12% no
  press. Botão preenchido sobe 1px no hover e aprofunda a sombra; volta no
  press. Card `interactive` sobe 3px e vai para elev-3. Linha de tabela recebe
  tint azul a 6%.
- **Desabilitado**: opacidade .45 e `pointer-events: none`.
- **Alvo de toque** ≥ 48px em todo controle; item de drawer 52px; botão `sm`
  40px apenas dentro de linhas de tabela.
- **Contraste**: texto em 16px mínimo; `--text-secondary` só em texto de apoio;
  chip sempre usa o par container / on-container (nunca cor de marca sobre
  pastel).
- **Cor nunca é o único sinal**: status tem rótulo em texto; ação destrutiva tem
  ícone + palavra; opção correta na autoria é checkbox, não cor.
- **Semântica**: `role="alert"` nos alerts, `role="progressbar"` com
  `aria-valuenow`, `aria-current="page"` no item ativo do drawer,
  `aria-selected` nas tabs, `aria-pressed` nos chips de filtro,
  `aria-label` em todo botão só-ícone.
- **Validação Laravel**: `.is-invalid` no campo + mensagem em
  `--critical` abaixo, com ícone. Nunca só a borda.
- **Movimento** respeita a ausência de necessidade: nada anima na entrada de
  conteúdo, então `prefers-reduced-motion` afeta apenas diálogo e drawer.

---

## 7. Plano de migração

| Fase | Onda | Escopo | Risco |
|---|---|---|---|
| 0 | Fundação | Pipeline de build, SCSS, fontes, paginador | Baixo |
| 1 | A | Layouts (2) + 16 componentes existentes | Médio |
| 2 | B | Parciais compartilhados e formulários reutilizáveis | Baixo |
| 3 | C | Telas index somente leitura (~15) | Baixo |
| 4 | D | Telas create/edit (~15) | Médio |
| 5 | E | Sala de aula, provas e fórum (JS-heavy) | **Alto** |
| 6 | F | Landing, login, convite | Baixo |
| 7 | Polish | Componentes novos e verificação final | Médio |

Princípios de execução, herdados do plano em `spec/front_migration/07`:

1. A camada de tokens é a fonte única de verdade de design.
2. Zero `style=` inline no estado final.
3. Zero JS artesanal onde a biblioteca já entrega (Modal, Toast, Dropdown).
4. 2+ ocorrências = componente.
5. `dusk=` imutável.
6. Toda tarefa termina com build + Dusk filtrado.

Ordem sugerida de adoção visual: tokens → shell (app bar, drawer, page header)
→ componentes de dados → telas de listagem → formulários → telas JS-heavy →
públicas.

---

## 8. Contrato `dusk=` — restrição dura

**316 seletores `dusk="..."`** em `resources/views/` precisam sobreviver
**verbatim**: nenhum renomeado, nenhum removido, nenhum movido para outro
elemento com semântica diferente. 76 deles estão na camada de componente
(`spec/front_migration/02-component-inventory.md` § 7), o resto por tela
(`03-screen-inventory.md` § 1–18). 25 arquivos em `tests/Browser/` dependem
deles.

Regras práticas ao reescrever um componente:

- O `dusk` vive no mesmo nó semântico de antes (o `<button>`, não o wrapper).
- Wrappers novos introduzidos pela linguagem (`.ds-field-shell`,
  `.ds-table-wrap`, `.ds-card-body`) **não** recebem `dusk`.
- Listas arrastáveis mantêm `data-reorder-url` e `data-id`, pois o JS de
  reordenação é compartilhado por módulos, lições e questões.
- Cada fase termina com `artisan dusk` filtrado nas telas tocadas.

---

## 9. Origem

Repositório: `rafadepaula/plataforma_ead`, branch `main`.
Documentos lidos: `README.md`, `spec/front_migration/00`, `01`, `03` (índice),
`spec/specs/README.md`, e as views
`quizzes/edit.blade.php`, `quizzes/partials/_question-form.blade.php`,
`quizzes/partials/_question-list.blade.php`,
`quizzes/attempts/show.blade.php`, `modules/lessons/_form.blade.php`,
`courses/modules/_list.blade.php`.
Design system: **Plataforma EAD Design System** (`_ds/`), com os UI kits
`lms_app` e `public_site` como referência de composição.
