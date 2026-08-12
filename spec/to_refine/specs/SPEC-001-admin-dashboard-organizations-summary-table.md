# SPEC-001: Tabela de resumo das Organizações no Dashboard do Admin

## Descrição
O Dashboard do Admin (`admin.dashboard`) hoje entrega apenas KPIs agregados (alunos ativos, certificados, percentual de conclusão, número de cursos), a lista de matrículas recentes e os botões de exportação — todos no escopo resolvido pelo contexto atual. Falta ao Admin uma visão comparativa entre Organizações.

Esta funcionalidade adiciona ao Dashboard uma tabela de resumo listando as Organizações cadastradas na plataforma, cada linha com indicadores próprios daquela Organização: número de alunos, número de cursos, certificados emitidos e demais métricas a definir no refinamento.

A tabela é exclusiva do perfil Admin em contexto global (sem Impersonate Org ativo) — um Gestor, restrito à própria Organização, não tem visão cross-org. Serve como ponto de entrada de diagnóstico: identificar rapidamente Organizações inativas, sem cursos publicados ou sem certificados emitidos.

## Critérios de Aceitação
- [ ] O Admin, no Dashboard, vê uma tabela listando as Organizações cadastradas na plataforma.
- [ ] Cada linha exibe o nome da Organização e, no mínimo, número de alunos, número de cursos e certificados emitidos.
- [ ] O conjunto final de colunas é definido no refinamento e cada métrica tem definição inequívoca (ex.: "alunos" conta apenas usuários com papel `aluno` e status `ativo`).
- [ ] A tabela reflete todas as Organizações, inclusive as que não possuem nenhum dado (zeros explícitos, não linhas ausentes).
- [ ] Um Gestor não vê essa tabela.
- [ ] Os KPIs, as matrículas recentes e as exportações já existentes no Dashboard permanecem funcionando sem alteração.
- [ ] A tabela não introduz consultas N+1 conforme o número de Organizações cresce.

## Origem
Demanda original:

> Dado que estou logado com perfil: Admin
> Estou na tela: Dashboard
> Estou vendo:
>     - alunos ativos, certificados, porcentagem de conclusão, número de cursos.
>     - matrículas recentes
>     - exportar matrículas e certificados
>
> Gostaria de estar vendo:
>     - resumo das organizações, uma tabela mostrando uma lista de organizações que estão cadastradas na plataforma e alguns dados sobre elas: número de alunos, cursos, certificado emitidos, etc.

Arquivo de origem: `spec/to_refine/001/admin.md`
