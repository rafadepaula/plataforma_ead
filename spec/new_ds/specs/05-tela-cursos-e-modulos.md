# Tela — Cursos e módulos

**Kicker/título:** *Gestão / Cursos e módulos*
**Views de origem:** `courses/index.blade.php`

## Objetivo
`FilterBar` (busca + status).

## Layout e componentes
`DataTable`: curso (título + "2 módulos · 13 aulas" em caption), carga, alunos, status em chip, ações agrupadas (Abrir tonal / Editar ghost com borda / Remover em --critical). `Pagination` estilizada.

## Estados
Remover sempre passa por `ConfirmModal`. Curso com matrícula ativa é protegido — mensagem explícita, não só botão desabilitado.

## Contrato `dusk`
Linha da tabela, botões de ação por curso, paginação. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
