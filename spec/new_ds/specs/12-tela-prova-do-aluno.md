# Tela — Prova do aluno

**Kicker/título:** *Aprendizado / Prova*
**Views de origem:** `student/quizzes/show.blade.php`

## Objetivo
Coluna de leitura 760px. Cronômetro em chip info na app bar.

## Layout e componentes
Alerta "Antes de começar" com instruções e tentativa atual. Questão a questão: overline "Questão 1 de 3", tipo em chip outline, enunciado h4, opções como alvos grandes (--surface-sunken, borda 1,5px, rádio/checkbox 22px), dissertativa em textarea ("Mínimo de 200 caracteres.").

## Estados
Envio único — sem re-submissão. Retorno em alerta menta com nota preliminar ("Sua nota preliminar é 80%.").

## Contrato `dusk`
Cronômetro, opções de questão, botão de envio final. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
