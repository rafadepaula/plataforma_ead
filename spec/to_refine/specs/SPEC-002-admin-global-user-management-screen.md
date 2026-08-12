# SPEC-002: Tela exclusiva de administração global de usuários

## Descrição
A tela de usuários existente (`users.index`) foi desenhada para operação dentro de uma Organização: lista apenas usuários com papel `aluno` ou `gestor`, restritos a um único `org_id`, e oferece somente editar e remover. Ela não serve à administração do sistema.

Esta funcionalidade cria uma tela exclusiva do módulo de Admin, com a lista total de usuários da plataforma — todos os perfis, todas as Organizações — voltada à administração e não à operação. A tela oferece filtros (nome, e-mail, organização, status, tipo/papel, data de criação) e ações administrativas por usuário: ativar, desativar, excluir, visualizar o perfil completo e editar o perfil completo.

Distingue-se deliberadamente da tela operacional: escopo cross-org, inclusão de Admins e demais papéis, e ações de ciclo de vida do usuário que hoje não existem em lugar nenhum (ativar/desativar direto da listagem, visualização de perfil completo).

> Pré-requisito relacionado: hoje o Admin sequer alcança `users.index` sem Impersonate Org ativo. Isso é um defeito de acesso, reportado separadamente em `spec/bugs/BUG-005-users-index-unreachable-for-admin-without-impersonation.md`, e deve ser resolvido independentemente desta funcionalidade.

## Escopo dos filtros de status e papel (decidido)
A demanda original citava um status "pendente" e um tipo "professor" que não existem no modelo de dados. Decisão do solicitante: **os valores inexistentes ficam fora de escopo** — os filtros cobrem apenas o que já existe hoje. Nenhuma migração de enum e nenhum papel novo entram nesta funcionalidade.

- **Status:** apenas `active` e `inactive` (enum atual de `users.status`).
- **Papel:** apenas `admin`, `gestor` e `aluno` (valores atuais de `RolesEnum`).

## Critérios de Aceitação
- [ ] O Admin acessa uma tela de administração de usuários que lista usuários de todas as Organizações.
- [ ] A listagem inclui os três papéis existentes (admin, gestor e aluno), não apenas aluno/gestor.
- [ ] Cada linha identifica o usuário, sua Organização, seu papel, seu status e sua data de criação.
- [ ] É possível filtrar por nome, e-mail, organização, status (`active`, `inactive`), papel (`admin`, `gestor`, `aluno`) e data de criação.
- [ ] Os filtros de status e papel oferecem apenas os valores existentes no modelo — nenhum status "pendente" e nenhum papel "professor".
- [ ] Os filtros podem ser combinados e o resultado permanece paginado.
- [ ] O Admin consegue desativar um usuário ativo diretamente da tela.
- [ ] O Admin consegue ativar um usuário inativo diretamente da tela.
- [ ] O Admin consegue excluir um usuário, com confirmação explícita antes da execução.
- [ ] O Admin consegue visualizar o perfil completo de um usuário.
- [ ] O Admin consegue editar o perfil completo de um usuário.
- [ ] A tela é inacessível a Gestores e Alunos.
- [ ] A tela operacional existente (`users.index`) continua funcionando para o Gestor no escopo da própria Organização.
- [ ] Mudanças de status e exclusões geram registro de auditoria.

## Origem
Demanda original:

> Dado que estou logado com perfil: admin
> Estou na tela: (não consigo acessar)
> Estou vendo:
>     - nada, pois não está sendo possível acessar
>     - a tela de lista de alunos e usuários está visível apenas quando impersonate ativo
>
> Gostaria de estar vendo:
>     - lista todal de usuários e alunos de todo o sistema
>     - visualização e tela exclusiva para módulo de admin
>     - ou seja, uma tela exclusiva para a administração do sistema, com opções e filtros exclusivos
>     - filtros:
>         - nome
>         - email
>         - organização
>         - status (ativo, inativo, pendente)
>         - tipo (admin, aluno, professor, etc)
>         - data de criação
>     - opções exclusivas:
>         - desativar usuário
>         - ativar usuário
>         - excluir usuário
>         - ver perfil completo do usuário
>         - editar perfil completo do usuário

Arquivo de origem: `spec/to_refine/001/admin.md`
