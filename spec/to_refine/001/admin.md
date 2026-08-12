# Modelo

Dado que estou logado com perfil:
Estou na tela: 
Estou vendo:

Gostaria de estar vendo:

# Menu admin
Dado que estou logado com perfil: Admin
Estou na tela: Qualquer uma tela do sistema
Estou vendo:
    - links:
        - Dashboard
        - Organizações
        - Alunos & Usuários
        - Cursos
        - Redações pendentes
        - Moderação do fórum
        - Auditoria
        - Configurações
        - Meus Cursos

Gostaria de estar vendo:
    - links:
        - Dashboard
        - Organizações
        - Alunos & Usuários
        - Auditoria
        - Configurações

    - ou seja, diminuir o escopo de menu do admin por padrão
    - quando ativar o impersonate, deveria aparecer em uma subsessão:
        - "Impersonate"
            - Cursos
            - Redações pendentes
            - Moderações do fórum

# Top bar admin
Dado que estou logado com perfil: Admin
Estou na tela: Qualquer uma tela do sistema
Estou vendo:
    - barra de busca
    - botão de ajuda
    - meu perfil
    - sair

Gostaria de estar vendo:
    - se impersonate estiver ativo, mostrar badge da organização ativa e opção para desabilitar impersonate
        - quando desabilitar impersonate, automaticamente redirecionar para admin/dashboard
    - botão de ajuda
    - meu perfil
    - sair


# Dashboard admin
Dado que estou logado com perfil: Admin
Estou na tela: Dashboard
Estou vendo: 
    - alunos ativos, certificados, porcentagem de conclusão, número de cursos.
    - matrículas recentes
    - exportar matrículas e certificados

Gostaria de estar vendo:
    - resumo das organizações, uma tabela mostrando uma lista de organizações que estão cadastradas na plataforma e alguns dados sobre elas: número de alunos, cursos, certificado emitidos, etc.

# Lista de organizações - fluxo de remover
Dado que estou logado com perfil: admin
Estou na tela: /organizations
Estou vendo: 
    - botão de remover na tabela de listagem
    - ao clicar em remover, automaticamente a organização é deletada
    - quando deleta, uma mensagem "Organização removida com sucesso" é mostrada no topo depois de recarregar a página
    - existe um botão para esconder a mensagem, mas o botão não funciona e a mensagem nunca desaparece

Gostaria de estar vendo:
    - botão de remover na tabela de listagem
    - ao clicar em remover, mostrar modal de confirmação
    - ao confirmar, mostrar mensagem de sucesso e remover a organização da tabela
    - fazer o botão de esconder mensagem funcionar (corrigir bug)
    - ao cancelar, não remover a organização e não dar nenhum feedback, simplesmente fechar a modal

# Editar organização - visualização de logo desconfigurada
Dado que estou logado com perfil: admin
Estou na tela: /organizations/{id}/edit
Estou vendo: 
    - formulário completo de edição da organização
    - campo de logo está com legenda "logo atual: {nome do arquivo}"
    - não está mostrando o logo da organização

Gostaria de estar vendo:
    - formulário completo
    - logo da organização abaixo do campo de logo

# Lista de alunos e usuários - admin
Dado que estou logado com perfil: admin
Estou na tela: (não consigo acessar)
Estou vendo:
    - nada, pois não está sendo possível acessar
    - a tela de lista de alunos e usuários está visível apenas quando impersonate ativo

Gostaria de estar vendo:
    - lista todal de usuários e alunos de todo o sistema
    - visualização e tela exclusiva para módulo de admin
    - ou seja, uma tela exclusiva para a administração do sistema, com opções e filtros exclusivos
    - filtros:
        - nome
        - email
        - organização
        - status (ativo, inativo, pendente)
        - tipo (admin, aluno, professor, etc)
        - data de criação
    - opções exclusivas:
        - desativar usuário
        - ativar usuário
        - excluir usuário
        - ver perfil completo do usuário
        - editar perfil completo do usuário