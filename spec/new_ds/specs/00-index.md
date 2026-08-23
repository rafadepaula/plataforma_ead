# specs/ — índice

Specs de execução da migração para o Plataforma EAD Design System. Cada
arquivo é uma unidade de trabalho que um time pode pegar isoladamente. Todas
partem de `DESIGN.md` (tokens e visão geral) e do código-fonte real em
`rafadepaula/plataforma_ead`.

## Fundação

- `01-tokens-e-build.md` — SCSS → tokens CSS, pipeline, fontes, paginador.
- `02-inventario-de-componentes.md` — os 30 componentes, DE→PARA com props,
  variantes e notas de implementação.

## Shell e telas (ondas B–F, ver `17-plano-de-fases.md`)

- `03-shell.md` — app bar, drawer, page header, footer.
- `04-tela-dashboard.md`
- `05-tela-cursos-e-modulos.md`
- `06-tela-modulos-e-licoes.md`
- `07-tela-autoria-de-prova.md`
- `08-tela-correcao-de-redacoes.md`
- `09-tela-meus-cursos.md`
- `10-tela-sala-de-aula.md`
- `11-tela-aula-em-video.md`
- `12-tela-prova-do-aluno.md`
- `13-tela-forum.md`
- `14-tela-landing.md`
- `15-tela-login-e-convite.md`
- `16-mobile.md`

## Governança

- `17-plano-de-fases.md` — ondas, riscos, ordem de adoção.
- `18-contrato-dusk-e-testes.md` — regra dos 316 seletores, gate de Dusk por fase.
- `19-conteudo-e-acessibilidade.md` — tom de voz, foco, contraste, aria.

## Não cobertas nesta rodada

Organizações e usuários (admin), auditoria, moderação do fórum, certificados
do gestor, validação pública de certificado, configurações e perfil — ver
`DESIGN.md` §4.15. Não especificar sem confirmação do cliente.
