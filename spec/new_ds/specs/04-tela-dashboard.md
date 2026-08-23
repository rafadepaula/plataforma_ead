# Tela — Dashboard

**Kicker/título:** *Painel / Bom dia, Joana*
**Views de origem:** `dashboard/index.blade.php`

## Objetivo
Chips de período (7 dias / 30 dias / este ano) via `Chip`. Métricas de conclusão, alunos ativos etc.

## Layout e componentes
4 `StatCard` (alunos ativos, certificados emitidos, taxa de conclusão, cursos publicados), delta em chip menta, número em pt-BR ("+4,2%"). Grade 2fr/1fr: `Table` "Matrículas recentes" (avatar+e-mail, curso, progresso em barra 8px, status em chip) + coluna "Precisa da sua atenção" (redações, denúncias, certificados prontos) + "Cursos mais concluídos" em barras menta. Ações: Exportar CSV (tonal), Novo curso (primary).

## Estados
Sem dados ainda: `EmptyState` com próximo passo, nunca tabela vazia crua.

## Contrato `dusk`
Seletores da tabela de matrículas, dos StatCards e dos botões de ação. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
