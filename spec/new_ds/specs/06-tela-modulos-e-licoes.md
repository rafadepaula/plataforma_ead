# Tela — Módulos e lições

**Kicker/título:** *Gestão / Módulos*
**Views de origem:** `courses/modules/_list.blade.php`, `modules/lessons/_form.blade.php`

## Objetivo
Lista arrastável mantendo `data-reorder-url` e `ModuleReorder.js` intactos.

## Layout e componentes
Alça de arraste: ícone `grip-vertical` Lucide no lugar do caractere atual. Linha em `--surface-sunken`, elev-1, raio 14px. Formulário de lição: título, tipo de conteúdo, URL do YouTube com pré-visualização em pastel wash, upload de imagem e PDF, "Publicado" como `Switch`.

## Estados
Hint preservado verbatim: o servidor revalida o link no envio.

## Contrato `dusk`
`data-reorder-url` e `data-id` preservados — JS de reordenação é compartilhado com módulos, lições e questões. Não renomear. Lista exata em `spec/front_migration/03-screen-inventory.md` (seção
da tela) no repositório de origem — não renomear, não mover de nó.

## Critérios de aceite
- Conteúdo e comportamento idênticos ao Blade original; só a casca visual muda.
- Zero vermelho/laranja/amarelo, zero caixa-alta fora de overline.
- Todo texto em pt-BR, sentence case, segunda pessoa.
- `artisan dusk` filtrado nesta tela passa.
