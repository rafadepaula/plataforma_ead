<?php

return [
    [
        'target_page_key' => 'student.courses.index',
        'title' => 'Meus Cursos e Progresso',
        'slug' => 'meus-cursos-e-progresso',
        'category' => 'Para Alunos',
        'content' => <<<'MARKDOWN'
## Para que serve
Esta é a sua área principal como estudante. Aqui você visualiza todos os cursos em que está matriculado, acompanha a porcentagem de conclusão de cada um e tem acesso direto às salas de aula e aos seus certificados emitidos.

## Passo a passo
1. Acesse a lista de cursos ativos nos cards principais.
2. Observe a barra de progresso em cada card, indicando a porcentagem de aulas concluídas.
3. Clique em "Acessar Curso" para entrar na sala de aula e retomar seus estudos de onde parou.
4. Caso o curso esteja 100% concluído e com aprovação nas avaliações, clique no botão "Baixar Certificado" para obter seu documento digital em PDF.

## Regras e limites
- Apenas cursos com matrícula ativa ou já concluída aparecem nesta tela.
- Cursos cancelados ou temporariamente desativados pela instituição não ficam disponíveis para acesso.
- Seu progresso é salvo automaticamente a cada aula concluída.

## Dúvidas comuns
**Por que meu curso não aparece na lista?**
Verifique se a instituição de ensino confirmou sua matrícula ou se você utilizou o link de convite correto para se inscrever.

**Como sei se terminei o curso?**
A barra de progresso alcançará 100% e você receberá uma notificação no sistema confirmando a emissão do seu certificado.
MARKDOWN
    ],
    [
        'target_page_key' => 'classroom.show',
        'title' => 'Sala de Aula e Trilha de Aprendizagem',
        'slug' => 'sala-de-aula-e-trilha-de-aprendizagem',
        'category' => 'Para Alunos',
        'content' => <<<'MARKDOWN'
## Para que serve
Apresentar a trilha pedagógica completa do curso: a relação organizada de módulos, as aulas disponíveis, materiais de apoio e as avaliações programadas para o seu aprendizado.

## Passo a passo
1. Acompanhe a visão geral do curso com sua barra de progresso individual no topo.
2. Navegue pelos módulos temáticos para ver quais aulas já foram assistidas (marcadas com ícone de conclusão) e quais estão pendentes.
3. Clique em qualquer lição liberada para abrir o ambiente de estudo e assistir ao vídeo ou ler o documento.
4. Utilize o atalho "Fórum do Curso" para tirar dúvidas com colegas e professores sobre a matéria.

## Regras e limites
- Algumas aulas e módulos podem exigir a conclusão das etapas anteriores para liberação sequencial.
- Lições concluídas podem ser revistas quantas vezes você desejar durante o período do curso.

## Dúvidas comuns
**Posso rever aulas que já foram concluídas?**
Sim! Você pode clicar em qualquer lição já assistida para revisar o conteúdo a qualquer momento.

**Onde vejo os avisos do professor?**
Avisos gerais e discussões da matéria ficam centralizados na aba "Fórum" do curso.
MARKDOWN
    ],
    [
        'target_page_key' => 'classroom.lesson',
        'title' => 'Assistindo Aulas e Concluindo Lições',
        'slug' => 'assistindo-aulas-e-concluindo-licoes',
        'category' => 'Para Alunos',
        'content' => <<<'MARKDOWN'
## Para que serve
Espaço imersivo de estudo para assistir a videoaulas, consultar apostilas e materiais em PDF ou ler artigos didáticos desenvolvidos para o treinamento.

## Passo a passo
1. Caso a lição seja uma videoaula, dê o play no player de vídeo. O sistema salva o segundo exato em que você pausou para que possa retomar depois.
2. Para videoaulas, ao atingir 90% de reprodução do vídeo, o sistema marcará a lição como concluída automaticamente.
3. Se a lição for um documento PDF ou texto explicativo, leia o material até o final e clique no botão "Marcar como Concluída" ao término dos estudos.
4. Use os botões "Aula Anterior" e "Próxima Aula" para navegar confortavelmente pela trilha de aprendizagem.

## Regras e limites
- Lições em vídeo exigem assistir pelo menos 90% da duração total para computar a conclusão automática no sistema.
- Aceleradores de vídeo ou avançar o marcador diretamente para o final sem assistir ao conteúdo não ativam a conclusão automática.
- Para lições que contenham uma prova ou quiz, a conclusão só é atingida após a realização e aprovação da avaliação.

## Dúvidas comuns
**Se eu fechar o vídeo pela metade, perco o que assisti?**
Não. O player memoriza seu ponto de parada e retomará a aula exatamente desse momento quando você retornar.

**Posso baixar o arquivo PDF da aula?**
Os materiais em PDF contam com leitor interativo na tela. O download depende das permissões autorizadas pela instituição de ensino para o arquivo.
MARKDOWN
    ],
    [
        'target_page_key' => 'student.quizzes.show',
        'title' => 'Como Fazer Avaliações e Provas',
        'slug' => 'como-fazer-avaliacoes-e-provas',
        'category' => 'Provas',
        'content' => <<<'MARKDOWN'
## Para que serve
Ambiente oficial para realização de provas e testes de conhecimentos do curso. Aqui você confere as regras antes de iniciar, responde às questões propostas e submete suas respostas para correção.

## Passo a passo
1. Na tela preparatória, leia com atenção o tempo limite disponível, a nota mínima exigida e a quantidade de tentativas permitidas.
2. Quando estiver pronto e em um ambiente tranquilo, clique no botão "Iniciar Prova".
3. Se a prova for cronometrada, o contador regressivo começará a rodar no topo da página a partir desse instante.
4. Selecione ou digite suas respostas para cada pergunta apresentada.
5. Ao concluir todas as respostas, clique em "Finalizar e Entregar Avaliação".
6. Confirme o envio no aviso para consolidar sua tentativa.

## Regras e limites
- O cronômetro só começa a contar após você clicar conscientemente em "Iniciar Prova".
- Em provas com tempo limite, se o tempo esgotar antes da entrega manual, suas respostas marcadas serão enviadas automaticamente e a tentativa será consumida.
- Fechar a janela ou desconectar a internet com a prova em andamento **não pausa o cronômetro**.
- Se a prova possuir perguntas dissertativas (redação), sua nota final só será calculada após o professor corrigir o texto manualmente.

## Dúvidas comuns
**Se eu tirar uma nota baixa e tentar de novo, qual nota prevalece?**
O sistema sempre considera a sua maior nota (`MAX`) entre todas as tentativas válidas realizadas para efeito de aprovação.

**Onde vejo o gabarito das questões?**
O gabarito com as respostas corretas só fica visível na tela de resultados após a finalização da tentativa, e somente se a instituição tiver habilitado a visualização.
MARKDOWN
    ],
    [
        'target_page_key' => 'student.quizzes.result',
        'title' => 'Resultado da Avaliação e Gabarito',
        'slug' => 'resultado-da-avaliacao-e-gabarito',
        'category' => 'Provas',
        'content' => <<<'MARKDOWN'
## Para que serve
Conferir o desempenho obtido na prova logo após a entrega: percentual de acertos, status de aprovação, feedback das questões dissertativas e gabarito comentado.

## Passo a passo
1. Verifique o quadro principal com a mensagem "Você acertou X%" e a indicação de aprovação ou necessidade de nova tentativa.
2. Se a prova contiver questões dissertativas em análise, observe o aviso "Aguardando Correção Manual pelo Professor".
3. Caso o gabarito esteja habilitado, role a página para examinar cada questão, conferindo quais alternativas você assinalou e quais eram as respostas corretas.
4. Se ainda tiver tentativas disponíveis e desejar melhorar sua nota, clique em "Tentar Novamente".
5. Clique em "Voltar para o Curso" para seguir para as próximas lições da sua trilha.

## Regras e limites
- O gabarito nunca é exibido durante a realização da prova, apenas nesta página de resultado após a entrega.
- Caso o número máximo de tentativas permitidas tenha sido alcançado, o botão de tentar novamente ficará desabilitado.

## Dúvidas comuns
**Por que minha nota aparece como pendente?**
Se a avaliação continha questões de redação ou dissertativas, a nota só fica completa depois que o professor avaliar o seu texto na área dele.

**Posso refazer a prova se já fui aprovado?**
Se a instituição permitiu novas tentativas, você pode tentar novamente para melhorar sua nota. Caso sua nova nota seja menor, o sistema manterá a nota mais alta anterior.
MARKDOWN
    ],
    [
        'target_page_key' => 'forum.index',
        'title' => 'Fórum da Turma',
        'slug' => 'forum-da-turma',
        'category' => 'Fórum',
        'content' => <<<'MARKDOWN'
## Para que serve
Canal colaborativo de comunicação e troca de ideias entre estudantes, professores e tutores daquele curso específico. Espaço ideal para esclarecer dúvidas da matéria e compartilhar experiências.

## Passo a passo
1. Navegue pela lista de tópicos já criados pela comunidade, conferindo o título, autor e data da última resposta.
2. Utilize o campo de busca para pesquisar se sua dúvida já foi respondida em tópicos anteriores.
3. Clique sobre o título de qualquer conversa para ler as mensagens e participar com comentários.
4. Para abrir uma nova discussão que ainda não foi abordada, clique em "Novo Tópico".

## Regras e limites
- O fórum é restrito exclusivamente aos alunos matriculados e professores designados para aquele curso.
- Mantenha sempre um tom respeitoso e focado no conteúdo educacional do curso.
- Mensagens que infrinjam as regras de convivência podem ser denunciadas e removidas pela moderação.

## Dúvidas comuns
**Quem responde às minhas perguntas no fórum?**
Tanto os professores e instrutores do curso quanto seus colegas de turma podem interagir e ajudar a responder suas dúvidas.

**Recebo aviso quando alguém responder ao meu tópico?**
Sim! Você receberá uma notificação no ícone do sininho no topo da plataforma sempre que houver uma nova resposta em tópicos criados por você.
MARKDOWN
    ],
    [
        'target_page_key' => 'forum.create',
        'title' => 'Criar Novo Tópico no Fórum',
        'slug' => 'criar-novo-topico-no-forum',
        'category' => 'Fórum',
        'content' => <<<'MARKDOWN'
## Para que serve
Publicar uma nova dúvida, reflexão ou questionamento pedagógico no fórum do curso para ser debatido pela turma e pelos professores.

## Passo a passo
1. No campo "Título do Tópico", escreva um resumo claro da sua dúvida (ex.: "Dúvida sobre o cálculo no Módulo 2").
2. No campo "Mensagem", descreva detalhadamente a sua pergunta, mencionando a aula ou material ao qual ela se refere.
3. Revise o texto para garantir clareza e clique em "Publicar Tópico".
4. Seu tópico aparecerá no topo da lista do fórum para visualização de todos.

## Regras e limites
- Evite títulos genéricos como "Ajuda" ou "Urgente" para facilitar a localização por outros estudantes.
- Não insira dados sensíveis pessoais (senhas, telefones) no corpo da mensagem pública.

## Dúvidas comuns
**Posso editar o que escrevi depois de publicar?**
Sim, você pode editar o título ou o texto da sua postagem a qualquer momento usando a opção "Editar".
MARKDOWN
    ],
    [
        'target_page_key' => 'forum.show',
        'title' => 'Visualizando Tópicos e Respondendo',
        'slug' => 'visualizando-topicos-e-respondendo',
        'category' => 'Fórum',
        'content' => <<<'MARKDOWN'
## Para que serve
Ler as mensagens completas de uma discussão no fórum, acompanhar orientações enviadas pelos docentes e enviar suas próprias respostas e ponderações.

## Passo a passo
1. Leia a mensagem original no início da página e as respostas organizadas cronologicamente abaixo dela.
2. Respostas marcadas com selo oficial ou fixadas no topo indicam orientações emitidas pelos professores do curso.
3. Para colaborar com a conversa, digite sua mensagem no campo "Escrever uma resposta" no rodapé e clique em "Enviar Resposta".
4. Caso identifique algum comentário com conteúdo ofensivo, spam ou desrespeitoso, clique no ícone de bandeira "Denunciar Mensagem" para enviar à moderação.

## Regras e limites
- Suas respostas ficam visíveis imediatamente para todos os membros da turma.
- O histórico de edições de mensagens editadas fica acessível para transparência comunitária.
- As denúncias de conteúdo são confidenciais e avaliadas diretamente pelos professores e pela coordenação.

## Dúvidas comuns
**O que significa um comentário fixado?**
Indica que o professor do curso considerou aquela resposta como a solução definitiva ou um esclarecimento importante sobre o tema.
MARKDOWN
    ],
    [
        'target_page_key' => 'forum.edit',
        'title' => 'Editar Mensagem no Fórum',
        'slug' => 'editar-mensagem-no-forum',
        'category' => 'Fórum',
        'content' => <<<'MARKDOWN'
## Para que serve
Corrigir informações, ajustar a redação ou complementar dados em um tópico ou resposta que você publicou anteriormente no fórum.

## Passo a passo
1. Altere o título ou o conteúdo do texto no formulário de edição.
2. Certifique-se de que a mensagem editada continue clara e útil para a conversa.
3. Clique em "Salvar Alterações".
4. A mensagem exibirá o indicativo "Editado" junto à data da última alteração.

## Regras e limites
- Você só pode editar as publicações de sua própria autoria.
- Para assegurar a integridade das discussões pedagógicas, a plataforma mantém um histórico público de edições acessível pelo botão "Ver Histórico".

## Dúvidas comuns
**Outros alunos conseguem ver o que eu escrevi antes da edição?**
Sim. Clicando no histórico de edições é possível consultar as versões anteriores da mensagem, garantindo total transparência no ambiente acadêmico.
MARKDOWN
    ],
    [
        'target_page_key' => 'profile.edit',
        'title' => 'Meu Perfil e Segurança da Conta',
        'slug' => 'meu-perfil-e-seguranca-da-conta',
        'category' => 'Minha Conta',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerenciar seus dados cadastrais pessoais (nome completo, e-mail de contato, CPF) e alterar sua senha de acesso com protocolos avançados de proteção à sua conta.

## Passo a passo
1. No painel "Informações do Perfil", confira e atualize seu nome completo caso haja necessidade de correção documental.
2. Certifique-se de que seu CPF esteja preenchido corretamente, pois ele é obrigatório para a emissão válida de seus certificados.
3. Clique em "Salvar Alterações" para confirmar as mudanças cadastrais.
4. Para modificar sua senha, acesse o painel "Atualizar Senha": informe sua senha atual e digite a nova senha desejada duas vezes.
5. Clique em "Atualizar Senha".

## Regras e limites
- O CPF passa por verificação matemática de dígitos verificadores e não permite números fictícios ou duplicados.
- Ao alterar a sua senha com sucesso, o sistema **encerra imediatamente todas as outras sessões ativas** em outros computadores, tablets ou celulares por segurança.
- O e-mail cadastrado é a chave de acesso única que permite a você participar de cursos em diferentes organizações sem criar contas separadas.

## Dúvidas comuns
**Por que meu nome completo precisa estar correto?**
O nome gravado nesta tela é exatamente o nome que será impresso nos seus certificados de conclusão de curso.

**Se eu trocar minha senha, serei desconectado de outros aparelhos?**
Sim! Esta é uma medida de proteção proativa da plataforma para garantir que apenas você tenha controle do seu acesso.
MARKDOWN
    ],
];
