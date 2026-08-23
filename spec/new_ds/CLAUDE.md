# CLAUDE.md — Plataforma EAD, refactor de front-end

Projeto: migração visual completa de `rafadepaula/plataforma_ead` (Laravel + Blade)
para o **Plataforma EAD Design System** ("Material Bootstrap"). Este arquivo é
lido em toda conversa deste projeto — mantenha-o curto e vá direto ao ponto.

## Fonte única de verdade

- **Aparência**: design system em `_ds/plataforma-ead-design-system-618bb28f-.../`.
  Nunca invente cor, tipografia, espaçamento ou componente fora dele.
- **Comportamento, dados e vocabulário**: repositório `rafadepaula/plataforma_ead`
  (branch `main`), especialmente `spec/front_migration/` e as views Blade em
  `resources/views/`. Ver `github.md` para o mapeamento tela → arquivo.
- **Especificação consolidada**: `DESIGN.md` (tokens, DE→PARA de componentes,
  tela por tela, tom de voz, acessibilidade, fases, contrato `dusk`).
- **Specs de execução**: pasta `specs/` — um arquivo por componente/tela/fase,
  para times pegarem tarefas isoladas. Ver `specs/00-index.md`.

## Regras que não se negocia

1. **Vermelho, laranja e amarelo são proibidos.** Destrutivo usa `--critical`
   (azul-slate) + ícone + texto explícito — nunca matiz de alerta.
2. **Português do Brasil em tudo.** Segunda pessoa, voz institucional
   ("Você atingiu..."), nunca "nós". Números com vírgula decimal, datas
   `dd/mm/aaaa`.
3. **Sentence case em tudo**, inclusive chips e badges. Caixa-alta só no
   `overline`.
4. **316 seletores `dusk="..."` sobrevivem verbatim.** Nenhum renomeado,
   removido ou movido para um nó com semântica diferente. Wrappers novos
   (`.ds-field-shell`, `.ds-table-wrap` etc.) nunca recebem `dusk`. Toda
   tarefa termina com `artisan dusk` filtrado nas telas tocadas.
5. **Zero `style=` inline no estado final.** Zero JS artesanal onde a
   biblioteca (Modal, Toast, Dropdown) já resolve.
6. **2+ ocorrências = componente.** Não crie variante de uma linha só para
   uma tela.
7. Estado vazio sempre tem próximo passo. Estado degradado não culpa o
   usuário. Nada é "urgente" ou "crítico" — pendência é trabalho a fazer.

## Onde as coisas estão neste projeto

- `Apresentacao do Produto.dc.html` — deck de venda (funcionalidade, sem falar
  de design system), com painel de tweaks ao vivo.
- `Novo Design System - Telas.dc.html` — deck de referência interna, 22 slides,
  tokens e componentes.
- `DESIGN.md` — especificação completa da nova linguagem visual.
- `specs/` — specs de execução, uma por componente/tela/fase da migração.
- `_ds/` — bundle do design system (tokens CSS + componentes React).

## Ao continuar este trabalho

- Antes de especificar uma tela nova, leia a view Blade de origem
  (`github_read_files`) — não invente campo, validação ou estado que não
  exista no código.
- Ao adicionar uma spec, siga o template das existentes em `specs/` (Objetivo,
  Componentes, Layout, Estados, Contrato `dusk`, Critérios de aceite).
- Qualquer decisão de tela não cobertas em `DESIGN.md` §4.15 precisa de
  confirmação do cliente antes de ser desenhada.
