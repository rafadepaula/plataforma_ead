# CLAUDE.md — Plataforma EAD, refactor de front-end

Projeto: migração visual completa de `rafadepaula/plataforma_ead` (Laravel + Blade)
para o **LMS Core System** (design system "Plataforma EAD", Stitch). Este
arquivo é lido em toda conversa deste projeto — mantenha-o curto e vá direto
ao ponto.

## Fonte única de verdade

- **Aparência e tokens**: `DESIGN.md` na **raiz do repositório** (export do
  Stitch, projeto `10297532859767677570`). Inter, azul de ação `#2563EB`,
  superfícies Material 3, grade de 8px, container 1280px. Nunca invente cor,
  tipografia, espaçamento ou componente fora dele — consuma só `var(--*)`
  definidos lá.
- **Comportamento, dados e vocabulário**: repositório `rafadepaula/plataforma_ead`
  (branch `main`), especialmente `spec/front_migration/` e as views Blade em
  `resources/views/`. Ver `github.md` para o mapeamento tela → arquivo.
- **Specs de execução**: pasta `specs/` — um arquivo por componente/tela/fase,
  para times pegarem tarefas isoladas. Ver `specs/00-index.md`.

## Regras que não se negocia

1. **Todo valor visual vem de token** (`var(--primary)`, `var(--space-4)`, …).
   Nenhum hex, px de cor/espaço/raio fora da camada de tokens.
2. **Semântica de estado**: `--success #10B981` (conclusão), `--warning
   #F59E0B` (prazo), `--error #EF4444` (validação e destrutivo, sempre com
   ícone + texto explícito), informação em tinta primária tonal
   (`--primary-surface` + `--primary`).
3. **Português do Brasil em tudo.** Segunda pessoa, voz institucional
   ("Você atingiu..."), nunca "nós". Números com vírgula decimal, datas
   `dd/mm/aaaa`.
4. **Sentence case em tudo**, inclusive chips e badges.
5. **316 seletores `dusk="..."` sobrevivem verbatim.** Nenhum renomeado,
   removido ou movido para um nó com semântica diferente. Wrappers novos
   (`.ds-field-shell`, `.ds-table-wrap` etc.) nunca recebem `dusk`. Toda
   tarefa termina com `artisan dusk` filtrado nas telas tocadas.
6. **Zero `style=` inline no estado final.** Zero JS artesanal onde a
   biblioteca (Modal, Toast, Dropdown) já resolve.
7. **2+ ocorrências = componente.** Não crie variante de uma linha só para
   uma tela.
8. Estado vazio sempre tem próximo passo. Estado degradado não culpa o
   usuário. `--warning` sinaliza prazo, não pânico — pendência é trabalho a
   fazer.

## Onde as coisas estão neste projeto

- `Apresentacao do Produto.dc.html` — deck de venda (funcionalidade, sem falar
  de design system), com painel de tweaks ao vivo.
- `Novo Design System - Telas.dc.html` — deck de referência interna, 22 slides,
  tokens e componentes.
- `../DESIGN.md` — **fonte única da verdade** dos tokens e da linguagem
  visual (raiz do repositório).
- `specs/` — specs de execução, uma por componente/tela/fase da migração.
- `plataforma-ead-design-system-618bb28f-…/` — bundle do design system
  (tokens CSS + componentes React).

## Ao continuar este trabalho

- Antes de especificar uma tela nova, leia a view Blade de origem
  (`github_read_files`) — não invente campo, validação ou estado que não
  exista no código.
- Ao adicionar uma spec, siga o template das existentes em `specs/` (Objetivo,
  Componentes, Layout, Estados, Contrato `dusk`, Critérios de aceite).
- Qualquer decisão de tela não coberta pelas specs (ver "Não cobertas nesta
  rodada" em `specs/00-index.md`) precisa de confirmação do cliente antes de
  ser desenhada.
