# Tela — Prova do aluno

**Caption/título:** *Aprendizado / Prova*
**Views de origem:** `student/quizzes/show.blade.php`

## Objetivo
Coluna de leitura 760px. Cronômetro em chip primário tonal (`--primary-surface` + `--primary`) na app bar.

## Layout e componentes
Alerta "Antes de começar" com instruções e tentativa atual. Questão a questão: `caption-xs` "Questão 1 de 3", tipo em chip outline, enunciado em `headline-lg`, opções como alvos grandes (`--surface-container-low`, borda 1,5px `--border-subtle`, rádio/checkbox 22px), dissertativa em textarea ("Mínimo de 200 caracteres.").

## Estados
Envio único — sem re-submissão. Retorno em alerta `--success` com nota preliminar ("Sua nota preliminar é 80%.").

## Contrato `dusk`
Cronômetro, opções de questão, botão de envio final. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Chips sempre com par container / on-container; zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
