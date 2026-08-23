# Tela — Sala de aula

**Kicker/título:** *Sala de aula / <curso>*
**Views de origem:** `classroom/show.blade.php`

## Objetivo
Breadcrumb + kicker. Um `Card` por módulo.

## Layout e componentes
Cada aula é uma linha: ícone em círculo 44px (menta+check se concluída; azul+play/file-text/clipboard se pendente), título, meta ("Vídeo · 14 min"), chip "Prova" quando aplicável, chevron. Lateral: progresso ("8 de 13 aulas concluídas"), alerta informativo sobre certificado, instrutor com avatar 64px.

## Estados
—

## Contrato `dusk`
Linha de aula, ícone de status, link para a aula, progresso. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
