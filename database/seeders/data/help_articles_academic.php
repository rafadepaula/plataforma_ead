<?php

return [
    [
        'target_page_key' => 'courses.index',
        'audience' => 'gestor',
        'title' => 'Gestão de Cursos',
        'slug' => 'gestao-de-cursos',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Esta tela permite visualizar, filtrar e gerenciar todos os cursos pertencentes à sua organização. É o ponto de partida para estruturar conteúdos pedagógicos, acompanhar matrículas e gerenciar o ciclo de vida dos cursos.

## Passo a passo
1. Visualize a listagem com o título, carga horária, status de publicação e quantidade de alunos matriculados.
2. Utilize o botão "Novo Curso" no topo da página para iniciar a criação de uma nova capacitação.
3. Clique em "Acessar Curso" ou "Editar" na linha correspondente para gerenciar módulos, aulas ou alterar dados cadastrais.
4. Acesse o menu de ações rápidas para navegar diretamente para matrículas, professores vinculados ou regras de conclusão.

## Regras e limites
- Um curso só pode ser excluído se não possuir alunos com histórico de matrículas ativas ou certificados já emitidos.
- Cursos com status de rascunho (não publicados) não aparecem na listagem do Aluno.
- Todas as alterações são restritas à organização ativa do usuário logado.

## Dúvidas comuns
**Como publicar um curso para os alunos?**
Acesse a edição do curso e marque a opção de publicação, garantindo que pelo menos um módulo com lições esteja cadastrado.

**Posso transferir um curso para outra organização?**
Não. Cursos pertencem estritamente à organização em que foram criados devido ao isolamento multitenant da plataforma.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.create',
        'audience' => 'gestor',
        'title' => 'Criar Novo Curso',
        'slug' => 'criar-novo-curso',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar um novo curso na organização, definindo informações fundamentais como título institucional, ementa/descrição, carga horária estimada e imagem ilustrativa de capa.

## Passo a passo
1. Preencha o campo "Título" com um nome claro e objetivo para a capacitação.
2. No campo "Descrição", insira a ementa, objetivos pedagógicos e pré-requisitos do treinamento.
3. Informe a "Carga Horária" estimada em minutos ou horas conforme especificado no formulário.
4. Faça o upload de uma imagem de capa recomendada no formato JPEG ou PNG.
5. Clique em "Salvar Curso" para registrar o curso como rascunho.

## Regras e limites
- O título do curso é obrigatório e deve ter até 255 caracteres.
- A imagem de capa deve respeitar o limite máximo de tamanho e formatos permitidos pelo sistema de arquivos.
- O curso recém-criado nasce despublicado por padrão, permitindo que a estrutura de módulos e lições seja montada antes da liberação.

## Dúvidas comuns
**Posso alterar a carga horária depois de publicar?**
Sim, a carga horária pode ser ajustada a qualquer momento, mas certifique-se de que os certificados emitidos reflitam a carga horária correta.

**Onde adiciono os vídeos e materiais?**
Após salvar as informações básicas nesta tela, você será direcionado para a área de módulos e lições do curso.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.edit',
        'audience' => 'gestor',
        'title' => 'Editar Curso',
        'slug' => 'editar-curso',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar as configurações gerais de um curso existente, como título, descrição, carga horária, status de publicação e imagem de capa.

## Passo a passo
1. Modifique os campos de texto desejados (Título, Descrição ou Carga Horária).
2. Para substituir a imagem de capa, selecione um novo arquivo no campo correspondente.
3. Alterne o status de publicação para tornar o curso visível ou oculto para os alunos matriculados.
4. Clique em "Salvar Alterações" para persistir os novos dados.

## Regras e limites
- Despublicar um curso que já possui alunos em andamento não apaga o progresso dos estudantes, mas impede que novas aulas sejam iniciadas na área do aluno.
- Apenas usuários com perfil de Gestor da organização ou Administrador têm permissão para editar os dados.

## Dúvidas comuns
**A substituição da capa afeta os cursos em andamento?**
A nova capa é atualizada imediatamente no catálogo e na sala de aula para todos os alunos.

**Como excluir um curso?**
A exclusão fica disponível nas ações do curso, desde que nenhuma matrícula com progresso ou certificado esteja associada a ele.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.modules.index',
        'audience' => 'gestor',
        'title' => 'Estrutura de Módulos do Curso',
        'slug' => 'estrutura-de-modulos-do-curso',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Visualizar e ordenar a grade de módulos temáticos que compõem o curso. Permite organizar a trilha de aprendizagem lógica que o estudante percorrerá.

## Passo a passo
1. Visualize a listagem com todos os módulos cadastrados e a quantidade de lições em cada um.
2. Use o botão "Novo Módulo" para adicionar uma nova unidade de ensino.
3. Arraste e solte os módulos pela alça de reordenação para ajustar a sequência de exibição aos alunos.
4. Clique em "Gerenciar Lições" para entrar no conteúdo do módulo ou em "Editar" para alterar o nome do módulo.

## Regras e limites
- A ordem dos módulos define a sequência natural de navegação na sala de aula do aluno.
- Não é possível excluir um módulo que contenha lições vinculadas; é necessário remover ou mover as lições previamente.
- A reordenação via drag-and-drop salva a sequência de forma persistente.

## Dúvidas comuns
**Existe limite na quantidade de módulos por curso?**
Não há limite técnico pré-definido, recomendando-se dividir conteúdos extensos em unidades pedagógicas de 5 a 10 lições.

**Os alunos vêem módulos vazios?**
Módulos sem lições cadastradas ou com lições rascunho não são exibidos na sala de aula do aluno.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.modules.create',
        'audience' => 'gestor',
        'title' => 'Adicionar Módulo ao Curso',
        'slug' => 'adicionar-modulo-ao-curso',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar um novo módulo temático dentro de um curso específico, agrupando lições correlatas sob uma mesma unidade pedagógica.

## Passo a passo
1. No campo "Título do Módulo", insira o nome da unidade temática (ex.: "Módulo 1: Introdução aos Conceitos").
2. No campo "Descrição", resuma os objetivos e tópicos abordados nesta etapa.
3. Clique em "Salvar Módulo" para incluí-lo na estrutura do curso.

## Regras e limites
- O título do módulo é obrigatório e deve ter até 255 caracteres.
- O novo módulo é adicionado ao final da lista de módulos existentes, podendo ser reordenado posteriormente.
- O módulo herda automaticamente a organização proprietária do curso.

## Dúvidas comuns
**Posso mover um módulo para outro curso?**
Não. Os módulos são estritamente vinculados ao curso em que foram criados.

**O que acontece após salvar?**
Você será redirecionado para a lista de módulos do curso, onde poderá adicionar lições imediatamente.
MARKDOWN
    ],
    [
        'target_page_key' => 'modules.edit',
        'audience' => 'gestor',
        'title' => 'Editar Módulo',
        'slug' => 'editar-modulo',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Alterar o título ou a descrição de um módulo já existente no curso.

## Passo a passo
1. Atualize o campo "Título do Módulo" com o novo nome desejado.
2. Modifique o campo "Descrição" conforme necessário.
3. Clique em "Salvar Alterações" para atualizar os dados.

## Regras e limites
- A alteração do título do módulo é refletida imediatamente na navegação da sala de aula para todos os alunos.
- O vínculo do módulo com o curso pai permanece inalterado.

## Dúvidas comuns
**Editar o nome do módulo afeta o progresso dos alunos?**
Não. O progresso é registrado nas lições concluídas, portanto a edição de textos de cabeçalho do módulo não altera a porcentagem de conclusão.
MARKDOWN
    ],
    [
        'target_page_key' => 'modules.lessons.index',
        'audience' => 'gestor',
        'title' => 'Lições do Módulo',
        'slug' => 'licoes-do-modulo',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerenciar todas as lições pertencentes a um módulo: visualização da lista, reordenação da sequência de aulas, conferência dos tipos de mídia e acesso à edição.

## Passo a passo
1. Visualize as lições cadastradas, identificadas por tipo (Vídeo, PDF, Texto, Quiz).
2. Para alterar a sequência pedagógica das aulas, arraste os itens pela alça de reordenação.
3. Clique em "Nova Lição" para adicionar um novo conteúdo a este módulo.
4. Utilize o botão "Editar" para modificar o conteúdo ou "Excluir" para remover uma lição.

## Regras e limites
- Cada lição pode ter apenas uma avaliação (Quiz) associada.
- A ordem das lições define a progressão do estudante na sala de aula.
- Lições em vídeo com threshold de 90% exigem que o aluno assista à maior parte do conteúdo antes do marco de conclusão.

## Dúvidas comuns
**Posso misturar vídeos, PDFs e quizzes no mesmo módulo?**
Sim. Um módulo pode conter lições de formatos variados de acordo com a metodologia do curso.

**Se eu reordenar as lições, o aluno perde o progresso?**
Não. As conclusões registradas permanecem vinculadas ao ID de cada lição concluída.
MARKDOWN
    ],
    [
        'target_page_key' => 'modules.lessons.create',
        'audience' => 'gestor',
        'title' => 'Criar Nova Lição',
        'slug' => 'criar-nova-licao',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar uma nova aula dentro de um módulo, definindo o tipo de material didático (Vídeo MP4 ou YouTube, documento PDF, texto/HTML ou avaliação tipo Quiz).

## Passo a passo
1. Preencha o "Título da Lição" e uma breve descrição ou instruções ao estudante.
2. Selecione o "Tipo de Lição" desejado:
   - **Vídeo**: envie um arquivo MP4 local ou insira uma URL de vídeo válida do YouTube.
   - **PDF**: faça upload do documento didático para visualização embutida.
   - **Texto**: redija o conteúdo explicativo no editor.
   - **Quiz**: crie uma lição destinada a teste de conhecimentos.
3. Configure a duração estimada em minutos.
4. Clique em "Salvar Lição" para registrar a aula no módulo.

## Regras e limites
- URLs de vídeo do YouTube passam por validação e sanitização estrita de segurança antes de serem aceitas.
- Arquivos de vídeo e PDF enviados respeitam os limites de tamanho estabelecidos pelo servidor.
- Para lições do tipo Quiz, a criação das questões ocorre na tela de gerenciamento de perguntas após o cadastro da lição.

## Dúvidas comuns
**Como funciona a conclusão automática de vídeos?**
Quando o aluno assiste a 90% da duração do vídeo, o sistema registra automaticamente a lição como concluída e recalcula a barra de progresso.

**Posso incluir arquivos para download na lição?**
Lições do tipo PDF contam com visualizador integrado seguro na sala de aula.
MARKDOWN
    ],
    [
        'target_page_key' => 'lessons.edit',
        'audience' => 'gestor',
        'title' => 'Editar Lição',
        'slug' => 'editar-licao',
        'category' => 'Cursos',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar as informações, arquivos anexos ou links de mídia de uma lição já existente no módulo.

## Passo a passo
1. Edite o título e a descrição da lição conforme necessário.
2. Caso precise atualizar a mídia, selecione um novo arquivo PDF, faça upload de novo vídeo ou altere o link do YouTube.
3. Atualize a duração estimada da aula caso a duração tenha mudado.
4. Clique em "Salvar Alterações" para atualizar a lição.

## Regras e limites
- Alterar o arquivo ou link de vídeo de uma aula já concluída por alunos não remove as conclusões já registradas no histórico.
- Lições vinculadas a quizzes em andamento mantêm a integridade referencial com as tentativas existentes.

## Dúvidas comuns
**O que acontece se eu mudar o tipo de lição de vídeo para texto?**
O conteúdo de mídia anterior é desvinculado e os alunos passarão a ver o conteúdo textual na sala de aula.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.enrollments.index',
        'audience' => 'gestor',
        'title' => 'Gestão de Matrículas do Curso',
        'slug' => 'gestao-de-matriculas-do-curso',
        'category' => 'Matrículas e Convites',
        'content' => <<<'MARKDOWN'
## Para que serve
Acompanhar e gerenciar todos os estudantes inscritos no curso. Permite verificar o percentual de progresso individual, data de matrícula, status atual e realizar cancelamentos ou restaurações.

## Passo a passo
1. Consulte a tabela de matrículas com nome do aluno, e-mail, data de ingresso, progresso (%) e status.
2. Utilize o botão "Nova Matrícula" para inscrever um aluno manualmente.
3. Para cancelar o acesso de um estudante, clique no botão "Desmatricular" na linha correspondente.
4. Para reativar um aluno desmatriculado anteriormente, utilize a ação "Restaurar Matrícula".

## Regras e limites
- Desmatricular um aluno não apaga seu histórico de lições concluídas; se a matrícula for restaurada, o progresso anterior é preservado.
- Alunos com matrícula cancelada não conseguem acessar o conteúdo do curso nem emitir certificados.
- Apenas alunos pertencentes à mesma organização do curso podem ser listados e matriculados.

## Dúvidas comuns
**O que significa progresso 100%?**
Indica que o aluno concluiu todas as lições obrigatórias e foi aprovado nas avaliações do curso.

**Posso exportar a lista de matriculados?**
Sim, relatórios consolidados podem ser extraídos através do painel de relatórios da plataforma.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.enrollments.create',
        'audience' => 'gestor',
        'title' => 'Matricular Aluno Manualmente',
        'slug' => 'matricular-aluno-manualmente',
        'category' => 'Matrículas e Convites',
        'content' => <<<'MARKDOWN'
## Para que serve
Inscrever diretamente um estudante já cadastrado na organização em um curso específico, liberando o acesso imediato à sala de aula.

## Passo a passo
1. Selecione o aluno desejado na lista suspensa ou utilize o campo de busca por nome, e-mail ou CPF.
2. Verifique se o aluno já não possui matrícula ativa neste mesmo curso.
3. Confirme a ação clicando no botão "Confirmar Matrícula".
4. O aluno receberá uma notificação em sua conta e o curso passará a constar em sua aba "Meus Cursos".

## Regras e limites
- Não é possível criar matrículas duplicadas para o mesmo par Aluno-Curso.
- Se o aluno foi desmatriculado anteriormente, o sistema reativa a matrícula existente com seus registros históricos preservados.
- O estudante deve pertencer à mesma organização do curso.

## Dúvidas comuns
**E se o aluno ainda não tiver cadastro na plataforma?**
Você pode cadastrá-lo previamente no menu de Alunos, importá-lo via CSV ou enviar um Link de Convite Inteligente do curso.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.invitation-links.index',
        'audience' => 'gestor',
        'title' => 'Links de Convite do Curso',
        'slug' => 'links-de-convite-do-curso',
        'category' => 'Matrículas e Convites',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerenciar os links públicos de auto-inscrição do curso (`/convite/{token}`). Permite que novos ou existentes alunos ingressem no treinamento de forma autônoma.

## Passo a passo
1. Visualize os links gerados, com informações de token, limite de utilizações, contagem de usos atuais e data de expiração.
2. Clique no botão de cópia rápida para transferir a URL pública do convite para a área de transferência.
3. Utilize o botão "Novo Link de Convite" para gerar um novo token com regras específicas.
4. Desative ou exclua links que não devem mais receber novas inscrições.

## Regras e limites
- Links de convite que atingem o limite máximo de utilizações são automaticamente invalidados para novos acessos.
- Links com data de validade expirada retornam mensagem explicativa amigável ao usuário.
- O link realiza a matrícula imediata do estudante assim que o formulário de cadastro adaptativo é concluído.

## Dúvidas comuns
**O mesmo link pode ser enviado para várias pessoas?**
Sim, desde que você configure o link com múltiplos usos ou deixe o campo de limite em branco (ilimitado).

**Um usuário já cadastrado em outra organização pode usar o link?**
Sim. A plataforma possui arquitetura multi-org unificada: o estudante reaproveita seu e-mail e senha globais, ingressando na nova organização sem criar conta duplicada.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.invitation-links.create',
        'audience' => 'gestor',
        'title' => 'Criar Link de Convite Inteligente',
        'slug' => 'criar-link-de-convite-inteligente',
        'category' => 'Matrículas e Convites',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerar um novo token de convite com parâmetros personalizados de validade temporal e limite de inscrições para ingresso simplificado de estudantes.

## Passo a passo
1. No campo "Limite de Usos", informe a quantidade máxima de matrículas permitidas por este link (deixe vazio para usos ilimitados).
2. No campo "Data de Expiração", selecione a data e horário limite para aceitar inscrições.
3. Clique em "Gerar Link de Convite".
4. Copie a URL gerada e compartilhe-a com os participantes ou em canais de comunicação.

## Regras e limites
- O token gerado é único, criptograficamente seguro e não previsível.
- Toda inscrição realizada via link é registrada com auditoria de origem.
- O formulário público no destino (`/convite/{token}`) detecta automaticamente se o e-mail informado já possui conta na plataforma.

## Dúvidas comuns
**Posso cancelar um convite após ter enviado o link?**
Sim. Basta excluir o convite na listagem de links que qualquer tentativa de acesso posterior será bloqueada imediatamente.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.professors.index',
        'audience' => 'gestor',
        'title' => 'Docentes do Curso',
        'slug' => 'docentes-do-curso',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Vincular professores da organização à docência do curso, concedendo-lhes permissão para acompanhar turmas, corrigir redações e mediar tópicos no fórum.

## Passo a passo
1. Visualize a listagem de professores atualmente associados ao curso.
2. No seletor "Adicionar Professor", escolha um docente cadastrado na organização.
3. Clique em "Atribuir Docência" para efetivar o vínculo.
4. Para remover um professor da docência daquele curso, clique no botão "Desvincular".

## Regras e limites
- O usuário deve possuir o perfil de Professor (`RolesEnum::PROFESSOR`) cadastrado na mesma organização.
- Desvincular um professor não apaga as correções de redações nem as mensagens no fórum que ele realizou anteriormente.
- Um curso pode ter múltiplos professores docentes atribuídos simultaneamente.

## Dúvidas comuns
**O professor vinculado tem acesso aos dados de outros cursos?**
Não. O painel do professor restringe a visualização estritamente aos cursos aos quais ele foi formalmente vinculado.
MARKDOWN
    ],
    [
        'target_page_key' => 'gestor.students.index',
        'audience' => 'gestor',
        'title' => 'Diretório de Alunos',
        'slug' => 'diretorio-de-alunos',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Consultar e administrar todos os estudantes cadastrados na sua organização. Fornece visão consolidada de status cadastral, e-mails e histórico de matrículas.

## Passo a passo
1. Navegue pela listagem com nome, e-mail, status de ativação e quantidade de cursos do aluno.
2. Utilize o campo de busca no topo para localizar rapidamente estudantes por nome ou e-mail.
3. Clique em "Editar" para atualizar informações cadastrais ou desativar temporariamente o acesso do estudante.
4. Para cadastros em massa, utilize o botão de atalho "Importar CSV".

## Regras e limites
- O Gestor visualiza exclusivamente os alunos que possuem vínculo com a sua própria organização.
- Alunos com status inativo não conseguem efetuar login na plataforma.
- Alunos não podem ter seu perfil alterado para Administrador global através deste painel.

## Dúvidas comuns
**Como matricular um aluno que já aparece no diretório?**
Acesse o curso desejado em "Cursos", vá até a aba "Matrículas" e selecione o aluno pelo nome.
MARKDOWN
    ],
    [
        'target_page_key' => 'gestor.students.edit',
        'audience' => 'gestor',
        'title' => 'Editar Dados do Aluno',
        'slug' => 'editar-dados-do-aluno',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar informações cadastrais essenciais do estudante, como nome completo, e-mail e status ativo/inativo na organização.

## Passo a passo
1. Altere o campo "Nome Completo" conforme documentação oficial do aluno (importante para emissão correta de certificados).
2. Modifique o "E-mail", se necessário.
3. Alterne o status da conta entre "Ativo" e "Inativo".
4. Clique em "Salvar Alterações".

## Regras e limites
- A alteração do nome reflete nos certificados emitidos a partir deste momento.
- O e-mail deve ser único na plataforma ou pertencer ao cadastro consolidado do usuário.
- Inativar o aluno impede novas autenticações imediatamente.

## Dúvidas comuns
**Posso redefinir a senha do aluno aqui?**
O aluno pode utilizar o fluxo seguro de "Esqueci minha senha" na tela de login para redefinir sua senha com envio de e-mail verificado.
MARKDOWN
    ],
    [
        'target_page_key' => 'gestor.professors.index',
        'audience' => 'gestor',
        'title' => 'Diretório de Professores',
        'slug' => 'diretorio-de-professores',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Listar e administrar todos os professores registrados na organização que estão aptos a assumir turmas e mediação pedagógica.

## Passo a passo
1. Consulte os professores cadastrados, seus e-mails e cursos em que estão atuando.
2. Clique no botão "Novo Professor" para cadastrar um novo integrante do corpo docente.
3. Utilize o botão "Editar" para atualizar dados de contato ou status de acesso do docente.

## Regras e limites
- Apenas usuários com a atribuição do perfil Docente aparecem nesta lista.
- A exclusão de um professor só é autorizada caso ele não esteja atualmente atribuído como docente responsável por turmas ativas.

## Dúvidas comuns
**Como atribuo o professor a um curso?**
Acesse o curso desejado no menu "Cursos", clique em "Professores" e adicione o docente àquela grade curricular.
MARKDOWN
    ],
    [
        'target_page_key' => 'gestor.professors.create',
        'audience' => 'gestor',
        'title' => 'Cadastrar Novo Professor',
        'slug' => 'cadastrar-novo-professor',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Registrar um novo professor na organização, criando suas credenciais de acesso para que possa utilizar o Painel do Professor.

## Passo a passo
1. Preencha o "Nome Completo" do docente.
2. Informe um "E-mail" corporativo ou pessoal válido para contato e login.
3. Defina uma senha inicial temporária ou confirme a geração de acesso.
4. Clique em "Cadastrar Professor" para salvar o registro.

## Regras e limites
- O usuário é automaticamente associado ao perfil `professor` vinculado à sua organização.
- O e-mail informado deve ser válido e exclusivo.
- Após o cadastro, é recomendável vincular o professor aos cursos correspondentes.

## Dúvidas comuns
**O professor pode gerenciar alunos de outros cursos?**
Não. Os privilégios do professor são estritamente delimitados aos cursos aos quais ele está explicitamente vinculado.
MARKDOWN
    ],
    [
        'target_page_key' => 'gestor.professors.edit',
        'audience' => 'gestor',
        'title' => 'Editar Dados do Professor',
        'slug' => 'editar-dados-do-professor',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Manter atualizados o nome, e-mail institucional e status de atividade do professor na organização.

## Passo a passo
1. Modifique o campo "Nome Completo" ou "E-mail".
2. Alterne o status do professor entre "Ativo" e "Inativo".
3. Clique em "Salvar Alterações".

## Regras e limites
- A inativação do professor bloqueia o acesso ao Painel do Professor e à fila de correção de redações.
- As correções e participações no fórum efetuadas anteriormente são mantidas no histórico da plataforma.

## Dúvidas comuns
**Como retirar o professor de um curso sem inativá-lo?**
Vá até o curso em questão, acesse a aba "Professores" e clique em "Desvincular" apenas naquele treinamento.
MARKDOWN
    ],
    [
        'target_page_key' => 'users.import.create',
        'audience' => 'gestor',
        'title' => 'Importação de Alunos via CSV',
        'slug' => 'importacao-de-alunos-via-csv',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Realizar a inclusão em lote de grandes quantidades de estudantes na organização a partir de uma planilha CSV padronizada, com processamento em lotes (chunked) e relatório de validação.

## Passo a passo
1. Baixe o modelo de exemplo de arquivo CSV disponibilizado na tela.
2. Preencha as colunas obrigatórias: `name`, `email` e `cpf` (opcional).
3. Selecione o arquivo CSV preparado em seu computador.
4. Caso deseje matricular os alunos importados diretamente em um curso, selecione o curso de destino no campo opcional.
5. Clique em "Iniciar Importação" e acompanhe a barra de progresso do processamento em lotes.
6. Ao final, analise o resumo com a contagem de sucessos e linhas com inconsistências.

## Regras e limites
- O arquivo deve estar codificado em UTF-8 com separador por vírgula ou ponto-e-vírgula.
- Linhas com e-mails inválidos ou CPFs com dígitos verificadores matematicamente incorretos são rejeitadas com apontamento individual da linha sem interromper o lote.
- Contas de usuários já existentes na plataforma têm seu vínculo associado à organização atual sem duplicação de registro.

## Dúvidas comuns
**Qual o limite de linhas por arquivo?**
O sistema processa arquivos com centenas de linhas de forma assíncrona e em blocos seguros para evitar timeouts de servidor.

**O que acontece se uma linha contiver erro?**
As linhas válidas são importadas com sucesso e o sistema gera uma lista detalhada com o número das linhas que falharam e o motivo exato.
MARKDOWN
    ],
    [
        'target_page_key' => 'users.index',
        'audience' => 'gestor',
        'title' => 'Gestão Operacional de Usuários',
        'slug' => 'gestao-operacional-de-usuarios',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Tela de administração operacional de usuários utilizada por Administradores no contexto de uma organização ativa (impersonada), permitindo visualizar todos os membros daquela entidade.

## Passo a passo
1. Verifique a lista de usuários cadastrados sob a organização ativa selecionada no topo.
2. Observe os perfis atribuídos a cada membro (Gestor, Aluno, Professor).
3. Use os filtros de busca por nome, e-mail ou perfil para localizar registros.
4. Clique em "Criar Usuário" ou "Editar" para gerenciar acessos.

## Regras e limites
- Esta tela exige que o Administrador esteja operando sob o contexto de uma organização ativa.
- As modificações realizadas afetam apenas os membros do tenant em operação.

## Dúvidas comuns
**Qual a diferença entre esta tela e a Gestão Global de Usuários?**
Esta tela atua estritamente dentro da organização selecionada, enquanto a tela de Usuários do Sistema (`admin.users.index`) opera em nível global da plataforma.
MARKDOWN
    ],
    [
        'target_page_key' => 'users.create',
        'audience' => 'gestor',
        'title' => 'Cadastrar Usuário Operacional',
        'slug' => 'cadastrar-usuario-operacional',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Cadastrar um novo usuário diretamente dentro do tenant atualmente ativo, definindo nome, e-mail, senha e perfil operacional (Gestor, Professor ou Aluno).

## Passo a passo
1. Insira o nome completo do usuário.
2. Informe um endereço de e-mail válido e exclusivo.
3. Defina a senha de acesso e sua confirmação.
4. Escolha o perfil operacional apropriado para as funções que o membro desempenhará.
5. Clique em "Cadastrar Usuário".

## Regras e limites
- O usuário é automaticamente associado à organização que estiver ativa na sessão do administrador.
- O e-mail não pode estar em conflito com outros usuários sem validação.

## Dúvidas comuns
**Posso cadastrar outro administrador por aqui?**
Não. Administradores globais do sistema devem ser gerenciados através do menu "Usuários do Sistema" (`admin.users.*`).
MARKDOWN
    ],
    [
        'target_page_key' => 'users.edit',
        'audience' => 'gestor',
        'title' => 'Editar Usuário Operacional',
        'slug' => 'editar-usuario-operacional',
        'category' => 'Pessoas',
        'content' => <<<'MARKDOWN'
## Para que serve
Editar dados cadastrais, alterar papéis operacionais e ajustar o status de ativação de um usuário vinculado à organização atual.

## Passo a passo
1. Revise e edite o nome, e-mail e documento do usuário.
2. Ajuste o perfil operacional atribuído caso as responsabilidades do usuário tenham mudado.
3. Marque ou desmarque o status de ativação da conta.
4. Clique em "Salvar Alterações".

## Regras e limites
- Usuários inativados perdem imediatamente a capacidade de navegar na área restrita.
- A alteração de papéis é restrita aos papéis válidos da organização (Gestor, Aluno, Professor).

## Dúvidas comuns
**Alterar o perfil de Aluno para Gestor concede acesso a outros cursos?**
Sim. Como Gestor da organização, o usuário passa a ter privilégios de gerenciamento sobre todos os cursos daquele tenant.
MARKDOWN
    ],
];
