# Tela — Sala de aula

**Caption/título:** *Sala de aula / <curso>*
**Views de origem:** `classroom/show.blade.php`

## Objetivo
Breadcrumb + caption. Um `Card` por módulo.

## Layout e componentes
Cada aula é uma linha: ícone em círculo 44px (`--success` com check se concluída; `--primary-surface` com ícone `--primary` play/file-text/clipboard se pendente), título, meta ("Vídeo · 14 min") em `caption-xs`, chip "Prova" quando aplicável, chevron. Lateral: progresso ("8 de 13 aulas concluídas"), alerta primário tonal sobre certificado, instrutor com avatar 64px.

## Estados
—

## Contrato `dusk`
Linha de aula, ícone de status, link para a aula, progresso. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Chips sempre com par container / on-container; zero caixa-alta.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
