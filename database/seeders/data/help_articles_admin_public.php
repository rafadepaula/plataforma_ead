<?php

return [
    [
        'target_page_key' => 'professor.dashboard',
        'audience' => 'professor',
        'title' => 'Painel do Professor',
        'slug' => 'painel-do-professor',
        'category' => 'Professor',
        'content' => <<<'MARKDOWN'
## Para que serve
Ponto central de trabalho do docente na plataforma. Reúne a visão geral das turmas sob sua responsabilidade pedagógica, alerta de redações e avaliações pendentes de correção e atalhos rápidos para o fórum das disciplinas.

## Passo a passo
1. Visualize os indicadores de turmas ativas e o número total de alunos acompanhados por você.
2. Verifique o card de alertas de "Redações Aguardando Correção" para identificar se há entregas recentes de estudantes.
3. Clique em "Ir para Correções" para abrir a fila de dissertativas e atribuir notas e feedbacks.
4. Utilize a listagem de cursos no rodapé para acessar diretamente as salas de aula e os fóruns em que você atua.

## Regras e limites
- O professor visualiza exclusivamente os dados, alunos e redações dos cursos aos quais foi explicitamente vinculado pelo gestor da organização.
- O docente não tem acesso a configurações administrativas de faturamento, dados fiscais ou outros cursos da instituição.

## Dúvidas comuns
**Por que não vejo um curso recém-criado na minha lista?**
O gestor da organização precisa atribuir você formalmente como docente na aba "Professores" dentro das configurações daquele curso.

**Posso emitir certificados manualmente pelo painel do professor?**
A emissão é automatizada pelas regras pedagógicas do curso, garantindo isonomia em conformidade com as diretrizes da coordenação.
MARKDOWN
    ],
    [
        'target_page_key' => 'professor.courses.index',
        'audience' => 'professor',
        'title' => 'Cursos Atribuídos ao Professor',
        'slug' => 'cursos-atribuidos-ao-professor',
        'category' => 'Professor',
        'content' => <<<'MARKDOWN'
## Para que serve
Listar todos os cursos nos quais você está designado como professor responsável, permitindo consultar o andamento das turmas e participar ativamente das atividades didáticas.

## Passo a passo
1. Navegue pela lista de capacitações atribuídas a você.
2. Em cada curso, visualize a quantidade de alunos matriculados e o percentual de engajamento médio.
3. Clique em "Acessar Curso" para abrir o conteúdo programático ou em "Fórum" para interagir com as dúvidas enviadas pelos estudantes.

## Regras e limites
- A atribuição de docência é de competência do Gestor ou Administrador.
- Professores podem responder perguntas no fórum e fixar comentários com orientações oficiais para a turma.

## Dúvidas comuns
**Como adicionar material complementar a um curso?**
A inclusão de novas lições e módulos pode ser realizada em alinhamento com a gestão pedagógica da sua organização.
MARKDOWN
    ],
    [
        'target_page_key' => 'admin.dashboard',
        'audience' => 'admin',
        'title' => 'Painel Geral do Administrador',
        'slug' => 'painel-geral-do-administrador',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Painel executivo com métricas consolidadas de toda a infraestrutura da plataforma: total de organizações atendidas, usuários globais cadastrados, cursos ativos e volume de emissão de certificados.

## Passo a passo
1. Monitore os cartões de KPIs no topo da tela (Organizações, Usuários Ativos, Cursos e Certificados).
2. Utilize o seletor "Organização Ativa" no cabeçalho caso deseje operar em nome de um tenant específico (modo impersonação).
3. Consulte o gráfico de novos cadastros e conclusões de cursos no período recente.
4. Utilize os botões de exportação consolidada para extrair relatórios gerenciais em formato CSV.

## Regras e limites
- O acesso a este painel é estritamente restrito a usuários com o papel de Administrador do Sistema (`RolesEnum::ADMIN`).
- Administradores operam em nível de governança global quando nenhum tenant está selecionado na sessão.

## Dúvidas comuns
**O que acontece quando o Administrador ativa a impersonação de uma organização?**
O sistema passa a carregar as visões operacionais daquela organização específica, permitindo ao Administrador auxiliar gestores locais sem sair da plataforma.
MARKDOWN
    ],
    [
        'target_page_key' => 'organizations.index',
        'audience' => 'admin',
        'title' => 'Gestão de Organizações (Tenants)',
        'slug' => 'gestao-de-organizacoes-tenants',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar, listar e gerenciar as organizações e instituições de ensino parceiras que utilizam o ambiente multitenant da plataforma de forma isolada e segura.

## Passo a passo
1. Visualize a listagem com nome da organização, slug identificador, data de criação e status.
2. Clique em "Nova Organização" para cadastrar uma nova entidade com seu ambiente segregado.
3. Clique em "Editar" para atualizar dados cadastrais ou "Impersonar" para gerenciar diretamente o ecossistema daquela entidade.

## Regras e limites
- Cada organização possui isolamento estrito de dados através de `org_id` e escopo global (`OrgScope`).
- A exclusão de uma organização é bloqueada caso existam cursos, matrículas ou registros vinculados a ela.

## Dúvidas comuns
**Usuários de uma organização têm acesso aos dados de outra?**
Não. O isolamento lógico multitenant impede que qualquer gestor, professor ou aluno visualize cursos, materiais ou membros de outras instituições.
MARKDOWN
    ],
    [
        'target_page_key' => 'organizations.create',
        'audience' => 'admin',
        'title' => 'Criar Nova Organização',
        'slug' => 'criar-nova-organizacao',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Registrar uma nova instituição de ensino ou empresa no ecossistema da plataforma, preparando a estrutura para receber seus próprios gestores, cursos e estudantes.

## Passo a passo
1. No campo "Nome da Organização", informe a razão social ou nome de apresentação da instituição.
2. Defina o "Slug" amigável que identificará a organização no sistema.
3. Preencha as informações complementares e selecione o status inicial da entidade.
4. Clique em "Salvar Organização".

## Regras e limites
- O slug deve ser único em toda a plataforma e composto por letras minúsculas e hífens.
- A criação de uma organização gera um identificador único imutável (`org_id`).

## Dúvidas comuns
**Como atribuir o primeiro gestor da nova organização?**
Após criar a organização, cadastre o usuário em "Usuários do Sistema" atribuindo-o à nova instituição com o papel de Gestor.
MARKDOWN
    ],
    [
        'target_page_key' => 'organizations.edit',
        'audience' => 'admin',
        'title' => 'Editar Organização',
        'slug' => 'editar-organizacao',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar dados cadastrais, denominação ou status de operação de uma instituição já registrada no sistema.

## Passo a passo
1. Altere o nome da organização ou informações de contato.
2. Ajuste as opções de status conforme necessário.
3. Clique em "Salvar Alterações".

## Regras e limites
- Alterações no nome da instituição são refletidas imediatamente nos cabeçalhos e telas dos usuários vinculados àquele tenant.

## Dúvidas comuns
**Posso alterar o slug de uma organização existente?**
Recomenda-se cautela ao alterar o slug caso existam links de convite e acessos dependentes da identificação prévia.
MARKDOWN
    ],
    [
        'target_page_key' => 'admin.users.index',
        'audience' => 'admin',
        'title' => 'Gestão Global de Usuários do Sistema',
        'slug' => 'gestao-global-de-usuarios-do-sistema',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Visualizar e filtrar todos os usuários cadastrados na base global da plataforma, permitindo a governança completa sobre contas, papéis e organizações de origem.

## Passo a passo
1. Consulte a lista de usuários com nome, e-mail, organização vinculada, papéis associados e status de atividade.
2. Utilize os filtros superiores para refinar a busca por papel (Admin, Gestor, Professor, Aluno) ou por instituição.
3. Clique em "Visualizar" para checar o histórico detalhado da conta ou em "Editar" para atualizar papéis e status.
4. Clique em "Novo Administrador / Usuário" para cadastrar novos membros com privilégios específicos.

## Regras e limites
- Exclusiva para o Administrador global do sistema.
- Apenas Administradores têm permissão para promover outros usuários a administradores do sistema.

## Dúvidas comuns
**Um aluno pode pertencer a mais de uma organização?**
Sim. A arquitetura de autenticação unificada permite que o mesmo e-mail acesse múltiplas organizações sem duplicação de contas.
MARKDOWN
    ],
    [
        'target_page_key' => 'admin.users.show',
        'audience' => 'admin',
        'title' => 'Detalhes de Usuário do Sistema',
        'slug' => 'detalhes-de-usuario-do-sistema',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Examinar a ficha cadastral completa de um usuário: vínculos institucionais, papéis concedidos, histórico de acesso e cursos em andamento.

## Passo a passo
1. Verifique os dados pessoais (nome, e-mail, CPF, status).
2. Analise a tabela de papéis e permissões ativas associadas ao usuário.
3. Consulte as organizações às quais o usuário tem acesso e o histórico de auditoria correspondente.

## Regras e limites
- Tela estritamente para consulta e diagnóstico técnico pelo Administrador.

## Dúvidas comuns
**Como suspender o acesso deste usuário?**
Acesse o botão "Editar" e altere o status da conta para "Inativo".
MARKDOWN
    ],
    [
        'target_page_key' => 'admin.users.edit',
        'audience' => 'admin',
        'title' => 'Editar Usuário do Sistema',
        'slug' => 'editar-usuario-do-sistema',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar permissões globais, transferir ou associar vínculos institucionais e redefinir parâmetros de segurança para qualquer conta na plataforma.

## Passo a passo
1. Altere o nome, e-mail ou documento conforme solicitação legítima do titular.
2. Ajuste a atribuição de papéis (Admin, Gestor, Professor, Aluno).
3. Modifique a organização principal do usuário, caso aplicável.
4. Marque ou desmarque o status "Ativo" da conta.
5. Clique em "Salvar Alterações".

## Regras e limites
- Não é possível revogar o próprio perfil de Administrador da conta que estiver logada no momento da alteração.
- A inativação de uma conta bloqueia imediatamente o login em todos os serviços da plataforma.

## Dúvidas comuns
**Como redefinir a senha do usuário por aqui?**
Você pode gerar uma senha provisória ou instruir o usuário a utilizar a redefinição de senha segura por e-mail na tela de login.
MARKDOWN
    ],
    [
        'target_page_key' => 'admin.audit-logs.index',
        'audience' => 'admin',
        'title' => 'Trilha de Auditoria do Sistema',
        'slug' => 'trilha-de-auditoria-do-sistema',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Monitorar com precisão cirúrgica todas as ações sensíveis e mutações de dados ocorridas na plataforma: criações, atualizações, exclusões, logins, revogações de certificados e alterações de permissões.

## Passo a passo
1. Examine a tabela cronológica com data/hora, ator responsável (usuário e IP), tipo de evento, modelo afetado e organização.
2. Clique no botão "Ver Diff" em qualquer registro para abrir a modal comparativa com as alterações exatas (valores anteriores e novos valores em formato JSON).
3. Utilize os filtros por período, tipo de evento ou usuário para investigações pontuais.
4. Clique em "Exportar CSV" para gerar um arquivo estruturado para fins de compliance e conformidade.

## Regras e limites
- Registros de auditoria são **imutáveis**: o sistema não permite edição ou exclusão manual individual de logs.
- O mecanismo de retenção (`audit-logs:prune`) expurga automaticamente apenas registros que ultrapassarem a janela configurada de retenção legal.
- Eventos críticos do sistema e falhas de autenticação são registrados independentemente de tenant.

## Dúvidas comuns
**O que é o "Diff" da auditoria?**
É a demonstração campo a campo do que mudou no registro, destacando exatamente o valor que existia antes e como ficou após a modificação.

**Quem tem acesso a esta tela?**
Exclusivamente Administradores do Sistema. Nenhum gestor ou professor possui acesso aos logs globais de auditoria.
MARKDOWN
    ],
    [
        'target_page_key' => 'settings.edit',
        'audience' => 'admin',
        'title' => 'Configurações Globais do Sistema',
        'slug' => 'configuracoes-globais-do-sistema',
        'category' => 'Administração',
        'content' => <<<'MARKDOWN'
## Para que serve
Configurar os parâmetros técnicos fundamentais da plataforma: servidor de e-mails (SMTP institucional), identidade visual padrão (logo e favicons) e assinaturas digitais utilizadas na emissão de certificados.

## Passo a passo
1. Na seção "E-mail (SMTP)", preencha o host, porta, credenciais e e-mail remetente para envio de notificações e links de recuperação de senha.
2. Na seção "Identidade Visual", faça upload do logotipo padrão da aplicação.
3. Na seção "Certificados", insira a imagem da assinatura digitalizada do diretor ou responsável técnico que sairá nos certificados globais.
4. Clique em "Salvar Configurações".

## Regras e limites
- As configurações definidas nesta tela servem como padrão global (`null org_id`).
- Organizações podem possuir overrides específicos para suas marcas e assinaturas quando configurado pela administração.

## Dúvidas comuns
**Como testar se o envio de e-mails está funcionando?**
Após preencher as credenciais de SMTP, utilize a função de teste de envio para verificar a entrega de e-mails na caixa de entrada.
MARKDOWN
    ],
    [
        'target_page_key' => 'org.help.artigos.index',
        'audience' => 'gestor',
        'title' => 'Gestão de Artigos da Central de Ajuda',
        'slug' => 'gestao-de-artigos-da-central-de-ajuda',
        'category' => 'Central de Ajuda',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerenciar todos os artigos de ajuda da plataforma: criar novos conteúdos informativos, editar explicações existentes e vincular artigos a telas do sistema para alimentar os botões contextuais de ajuda.

## Passo a passo
1. Consulte a lista de artigos cadastrados, identificando o título, categoria, tela associada (`target_page_key`) e escopo (Global ou Organização).
2. Clique no botão "Novo Artigo de Ajuda" para redigir uma nova orientação.
3. Utilize o botão "Editar" para atualizar conteúdos ou ajustar regras descritas.
4. Para visualizar como o artigo aparecerá na wiki, clique sobre o título.

## Regras e limites
- **Administradores** podem criar artigos globais (`org_id = null`) visíveis para todas as instituições, bem como artigos específicos de qualquer organização.
- **Gestores** podem criar e gerenciar artigos exclusivamente para a sua própria organização; artigos de outros tenants não são visíveis nem editáveis.
- Se uma organização criar um artigo com a mesma chave de tela (`target_page_key`) de um artigo global, o artigo da organização terá prioridade na modal contextual e na wiki para os alunos daquele tenant.

## Dúvidas comuns
**O que é o target_page_key?**
É o identificador da rota da tela onde o botão de ajuda contextual exibirá o conteúdo do artigo (por exemplo, `courses.index` para a listagem de cursos). Se deixar em branco, o artigo constará apenas na wiki pública.
MARKDOWN
    ],
    [
        'target_page_key' => 'org.help.artigos.create',
        'audience' => 'gestor',
        'title' => 'Criar Artigo de Ajuda',
        'slug' => 'criar-artigo-de-ajuda',
        'category' => 'Central de Ajuda',
        'content' => <<<'MARKDOWN'
## Para que serve
Redigir e publicar um novo artigo de ajuda com suporte a formatação rica em Markdown e pré-visualização em tempo real.

## Passo a passo
1. Preencha o "Título do Artigo" com uma frase clara sobre o assunto abordado.
2. Defina o "Slug" amigável (gerado automaticamente a partir do título).
3. Selecione ou digite a "Categoria" do artigo (ex.: Cursos, Avaliações, Alunos).
4. No campo "Chave da Tela (target_page_key)", selecione a rota do sistema à qual este artigo servirá de ajuda contextual (opcional).
5. Escreva o conteúdo no campo Markdown estruturado.
6. Utilize o botão "Visualizar Prévia" para conferir a renderização sem tags indesejadas.
7. Clique em "Salvar Artigo".

## Regras e limites
- O conteúdo é redigido em Markdown puro. Tags HTML perigosas como script ou iframe são automaticamente sanitizadas e removidas por segurança.
- O slug do artigo deve ser exclusivo em todo o banco de dados.

## Dúvidas comuns
**Como estruturar um bom artigo de ajuda?**
Siga o padrão oficial da plataforma: introduza a utilidade em "Para que serve", descreva o "Passo a passo" numerado, destaque as "Regras e limites" e finalize com "Dúvidas comuns".
MARKDOWN
    ],
    [
        'target_page_key' => 'org.help.artigos.edit',
        'audience' => 'gestor',
        'title' => 'Editar Artigo de Ajuda',
        'slug' => 'editar-artigo-de-ajuda',
        'category' => 'Central de Ajuda',
        'content' => <<<'MARKDOWN'
## Para que serve
Revisar o texto, alterar a categoria, atualizar orientações pedagógicas ou redefinir a tela vinculada de um artigo já publicado.

## Passo a passo
1. Modifique os campos de texto (Título, Categoria, Chave da Tela).
2. Atualize o conteúdo em Markdown com as novas orientações ou correções de processos.
3. Teste a renderização clicando em "Visualizar Prévia".
4. Clique em "Salvar Alterações".

## Regras e limites
- Gestores não podem editar artigos globais do sistema, mas podem criar um artigo próprio com a mesma chave para sobrepor a versão global na sua instituição.
- Administradores têm permissão para editar artigos globais e de qualquer tenant.

## Dúvidas comuns
**As alterações entram no ar imediatamente?**
Sim. Assim que salvas, as atualizações ficam disponíveis na wiki pública e nos botões de ajuda contextual.
MARKDOWN
    ],
    [
        'target_page_key' => 'help.index',
        'audience' => 'aluno',
        'title' => 'Portal da Central de Ajuda',
        'slug' => 'portal-da-central-de-ajuda',
        'category' => 'Central de Ajuda',
        'content' => <<<'MARKDOWN'
## Para que serve
Página inicial da base de conhecimento e documentação oficial da plataforma. Permite aos usuários pesquisar por palavras-chave ou navegar por categorias temáticas para encontrar respostas rápidas.

## Passo a passo
1. Utilize a barra de busca no centro da página para digitar o que procura (ex.: "certificado", "matrícula", "quiz").
2. Navegue pelos blocos de categorias (Cursos, Avaliações, Para Alunos, etc.).
3. Clique sobre qualquer artigo listado para ler as instruções detalhadas.

## Regras e limites
- Acesso totalmente público e livre de autenticação.
- Caso o usuário esteja autenticado em uma organização específica, os artigos e manuais personalizados daquela instituição são priorizados na listagem.

## Dúvidas comuns
**Como encontrar um artigo específico?**
Digite termos objetivos na barra de busca. A ferramenta localiza ocorrências tanto no título quanto no corpo dos artigos.
MARKDOWN
    ],
    [
        'target_page_key' => 'help.show',
        'audience' => 'aluno',
        'title' => 'Leitura de Artigo da Central de Ajuda',
        'slug' => 'leitura-de-artigo-da-central-de-ajuda',
        'category' => 'Central de Ajuda',
        'content' => <<<'MARKDOWN'
## Para que serve
Exibição completa de um manual ou artigo instrucional com formatação tipográfica limpa, links de navegação e atalho para retornar à listagem geral de tópicos.

## Passo a passo
1. Leia as orientações organizadas nas seções estruturadas.
2. Utilize os links da trilha de navegação (breadcrumb) no topo para voltar à categoria ou ao índice principal da Central de Ajuda.

## Regras e limites
- Artigos com slug inexistente ou pertencentes a outra organização restrita retornam página 404 por segurança.

## Dúvidas comuns
**Posso compartilhar o link de um artigo com outra pessoa?**
Sim! A URL do artigo é amigável e pode ser compartilhada diretamente por e-mail ou mensagens com quem precisar da orientação.
MARKDOWN
    ],
    [
        'target_page_key' => 'login',
        'audience' => 'aluno',
        'title' => 'Entrar na Plataforma (Login)',
        'slug' => 'entrar-na-plataforma-login',
        'category' => 'Acesso e Segurança',
        'content' => <<<'MARKDOWN'
## Para que serve
Portão de entrada seguro para todos os usuários cadastrados (Alunos, Professores, Gestores e Administradores) acessarem seus ambientes restritos de trabalho e estudo.

## Passo a passo
1. No campo "E-mail", digite o endereço eletrônico utilizado no seu cadastro.
2. No campo "Senha", informe sua senha secreta de acesso.
3. Marque a opção "Lembrar de mim" caso esteja utilizando seu computador pessoal e deseje manter a sessão ativa por mais tempo.
4. Clique no botão "Entrar".
5. Se você tiver esquecido sua senha, clique no link "Esqueceu sua senha?" para iniciar a recuperação.

## Regras e limites
- Usuários com status "Inativo" não conseguem se autenticar no sistema.
- Tentativas consecutivas de login incorreto ativam proteções de limitação de taxa (rate limiting) por segurança.
- O redirecionamento pós-login é automático com base no seu perfil: administradores vão para o painel administrativo, professores para a área docente e alunos para a tela "Meus Cursos".

## Dúvidas comuns
**Não lembro minha senha, o que fazer?**
Clique no link "Esqueceu sua senha?", digite seu e-mail cadastrado e você receberá um link seguro para criar uma nova senha imediatamente.
MARKDOWN
    ],
    [
        'target_page_key' => 'password.request',
        'audience' => 'aluno',
        'title' => 'Recuperação de Senha',
        'slug' => 'recuperacao-de-senha',
        'category' => 'Acesso e Segurança',
        'content' => <<<'MARKDOWN'
## Para que serve
Solicitar o envio de um e-mail com link exclusivo e temporário para redefinir a senha de acesso à plataforma de forma autônoma e segura.

## Passo a passo
1. Digite seu endereço de e-mail cadastrado no campo indicado.
2. Clique no botão "Enviar Link de Redefinição".
3. Acesse a caixa de entrada do seu e-mail, localize a mensagem enviada pela plataforma e clique no link de redefinição contido nela.

## Regras e limites
- O token enviado por e-mail tem validade temporária e expira após um período pré-determinado de segurança.
- Por motivos de privacidade, a mensagem de confirmação de envio é exibida mesmo se o e-mail não estiver na base, impedindo a enumeração de usuários por agentes maliciosos.

## Dúvidas comuns
**Não recebi o e-mail de redefinição, o que fazer?**
Verifique sua caixa de Spam ou Lixo Eletrônico. Certifique-se também de ter digitado exatamente o mesmo e-mail utilizado no seu cadastro.
MARKDOWN
    ],
    [
        'target_page_key' => 'password.reset',
        'audience' => 'aluno',
        'title' => 'Redefinir Nova Senha',
        'slug' => 'redefinir-nova-senha',
        'category' => 'Acesso e Segurança',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar uma nova senha de acesso definitiva para a sua conta após clicar no link de segurança recebido em seu e-mail.

## Passo a passo
1. Confirme seu endereço de e-mail no campo correspondente.
2. No campo "Nova Senha", crie uma senha forte e segura.
3. No campo "Confirmar Senha", redigite a mesma senha exatamente igual.
4. Clique em "Redefinir Senha".
5. Você será redirecionado para a tela de login com uma notificação de sucesso.

## Regras e limites
- A senha deve atender aos critérios mínimos de tamanho e complexidade exigidos pela plataforma.
- Cada link de redefinição é de uso único: após utilizado com sucesso, ele é imediatamente invalidado.

## Dúvidas comuns
**Posso usar uma senha que já usei antes?**
Recomenda-se utilizar uma combinação inédita para garantir a máxima segurança dos seus dados e certificados.
MARKDOWN
    ],
    [
        'target_page_key' => 'invitation.show',
        'audience' => 'aluno',
        'title' => 'Finalização de Cadastro por Convite',
        'slug' => 'aceite-de-convite-inteligente',
        'category' => 'Matrículas e Convites',
        'content' => <<<'MARKDOWN'
## Para que serve
Permitir que o aluno finalize o próprio cadastro através de um link de convite **único** (`/convite/{token}`), criado pela organização especificamente para ele, com e-mail e nome já preenchidos e imutáveis.

## Passo a passo
1. Abra o link de convite recebido da sua organização (cada link é pessoal e intransferível).
2. Confira seu nome e e-mail no formulário — eles vêm preenchidos e não podem ser alterados, pois identificam a conta criada para você.
3. Escolha uma senha (mínimo de 8 caracteres) e digite-a novamente para confirmar.
4. Marque a caixinha de concordância para a organização organizar seus cursos e dados de estudo.
5. Clique em "Salvar senha e começar" para ir direto para a sua lista "Meus Cursos".

## Regras e limites
- O link é de uso único: depois de finalizado o cadastro, ele deixa de funcionar.
- Links revogados, expirados ou já utilizados exibem o motivo e não aceitam novo uso.
- Se sua conta foi desativada pela organização, o convite não a reativa: procure o gestor.

## Dúvidas comuns
**Por que não consigo alterar meu e-mail no convite?**
Porque o convite é vinculado à conta que a organização criou para você; isso impede que o link seja usado para acessar a conta de outra pessoa.

**E se eu perder o link antes de usar?**
Solicite ao gestor da organização: ela pode copiar o mesmo link novamente ou gerar um novo (o anterior deixa de funcionar).
MARKDOWN
    ],
    [
        'target_page_key' => 'certificates.verify',
        'audience' => 'aluno',
        'title' => 'Validação Pública de Certificados',
        'slug' => 'validacao-publica-de-certificados',
        'category' => 'Certificados',
        'content' => <<<'MARKDOWN'
## Para que serve
Canal público e aberto para qualquer pessoa, empresa ou entidade fiscalizadora consultar a autenticidade e validade de certificados digitais emitidos pela plataforma.

## Passo a passo
1. Acesse a página ou aponte a câmera do seu smartphone para o QR Code impresso no certificado em PDF.
2. Caso tenha o código validador em mãos, digite o código hash SHA-256 no campo de busca e clique em "Validar Documento".
3. O sistema exibirá o resultado oficial da consulta:
   - **Certificado Válido**: exibe o nome do aluno, curso concluído, carga horária cumprida, instituição emissora e data exata da emissão.
   - **Certificado Revogado**: informa com transparência que o documento foi cancelado pela instituição, indicando a data de revogação e o motivo formal.
   - **Certificado Inexistente**: alerta que o código informado não consta na base de dados oficial.

## Regras e limites
- Esta consulta é 100% pública e não exige que o consultante possua login na plataforma.
- Certificados revogados nunca são ocultados ou apagados: sua situação de revogação é informada abertamente para combater fraudes.
- A autenticação é matematicamente infalsificável, baseada em assinatura SHA-256 única.

## Dúvidas comuns
**Onde localizo o código hash no meu certificado?**
O código validador alfanumérico está impresso na parte inferior do documento em PDF, acompanhado do QR Code de verificação rápida.
MARKDOWN
    ],
];
