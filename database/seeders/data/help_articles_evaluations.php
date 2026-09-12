<?php

return [
    [
        'target_page_key' => 'quizzes.create',
        'audience' => 'gestor',
        'title' => 'Criar Avaliação (Quiz)',
        'slug' => 'criar-avaliacao-quiz',
        'category' => 'Avaliações',
        'content' => <<<'MARKDOWN'
## Para que serve
Configurar a avaliação vinculada a uma lição do curso. Permite definir regras pedagógicas estritas, como limite de tempo, quantidade máxima de tentativas, nota mínima de aprovação e exibição de gabarito. O quiz é gerenciado 100% pela tela da lição: ao selecionar o tipo "Quiz", a própria lição exibe as regras e as questões, salvas juntas em um único envio.

## Passo a passo
1. Na lição, selecione o tipo "Quiz" — a tela da lição passa a exibir a configuração completa da avaliação (instruções, nota mínima, tentativas, limite de tempo e questões).
2. Defina a "Nota mínima para aprovação (%)" exigida (por exemplo, 70%).
3. Configure o campo "Limite de tempo (minutos)" caso a prova seja cronometrada (deixe em branco para tempo livre).
4. Estabeleça o "Máximo de tentativas" (deixe em branco para tentativas ilimitadas).
5. Marque a opção "Exibir gabarito ao aluno após envio" se desejar que o aluno veja as alternativas corretas após finalizar a tentativa.
6. Adicione as questões (enunciado, tipo e opções) na mesma tela e salve a lição — lição e quiz são persistidos em um único envio.

## Regras e limites
- Cada lição do tipo quiz suporta exatamente uma avaliação vinculada (`lesson_id` único).
- Se a prova for cronometrada, o cronômetro inicia estritamente após a confirmação expressa do aluno na tela de abertura.
- Fechar o navegador ou abandonar uma prova cronometrada em andamento não pausa o tempo: ao expirar o prazo, a tentativa é finalizada automaticamente e consome uma tentativa do aluno.
- As tentativas utilizam a nota mais alta (`MAX(score_percentage)`) entre todas as tentativas concluídas para fins de aprovação e emissão de certificado.

## Dúvidas comuns
**O que acontece se o aluno não atingir a nota mínima?**
Se houver tentativas restantes configuradas em `max_attempts`, o aluno poderá iniciar uma nova tentativa. Caso as tentativas se esgotem, ele não poderá refazer a prova.

**Quando o aluno visualiza o gabarito?**
Somente após a conclusão da tentativa e caso a opção "Exibir Gabarito" esteja habilitada pelo gestor. O gabarito nunca é exibido durante a realização da prova.
MARKDOWN
    ],
    [
        'target_page_key' => 'quizzes.edit',
        'audience' => 'gestor',
        'title' => 'Editar Avaliação e Questões',
        'slug' => 'editar-avaliacao-e-questoes',
        'category' => 'Avaliações',
        'content' => <<<'MARKDOWN'
## Para que serve
Atualizar as configurações da avaliação e gerenciar suas questões. Tudo isso é feito na própria tela da lição (tipo "Quiz"): adicionar perguntas, definir alternativas corretas, configurar pontuação e reordenar a sequência de perguntas.

## Passo a passo
1. Abra a lição do tipo "Quiz" em "Editar Lição" — as regras da avaliação (tempo limite, nota de corte, tentativas) ficam na mesma tela.
2. Na seção de questões, utilize o botão "+ Adicionar Questão" para adicionar um item.
3. Escolha o tipo de questão:
   - **Única escolha**: múltiplas alternativas com uma única resposta correta.
   - **Múltipla escolha**: múltiplas alternativas onde mais de uma opção correta deve ser assinalada.
   - **Verdadeiro ou Falso**: afirmações binárias simples.
   - **Dissertativa (correção manual)**: resposta em texto livre que exige correção manual do gestor ou professor.
4. Adicione as alternativas e marque claramente as opções corretas.
5. Reordene as questões com as setas ↑ e ↓ — a ordem definida na tela é a ordem aplicada ao salvar.
6. Clique em "Salvar Alterações" para persistir a lição e o quiz em um único envio.

## Regras e limites
- Questões de múltipla escolha simples exigem exatamente uma alternativa marcada como correta.
- Questões dissertativas não possuem alternativas; ao serem incluídas em uma prova, a tentativa do aluno ficará no status "Aguardando Correção" até que um professor ou gestor atribua a nota.
- A exclusão de uma questão só é permitida caso não haja tentativas já finalizadas vinculadas a ela, garantindo a integridade dos históricos dos estudantes.

## Dúvidas comuns
**Como a nota é calculada se a prova tiver questões dissertativas e de múltipla escolha?**
As questões automáticas são corrigidas instantaneamente, mas a nota final só é calculada e divulgada após a correção manual das dissertativas, mantendo todas as questões no mesmo denominador proporcional.

**Posso mudar a ordem das perguntas?**
Sim. A ordem configurada nesta tela é a mesma sequência em que as questões serão apresentadas ao estudante durante a resolução.
MARKDOWN
    ],
    [
        'target_page_key' => 'quiz-attempts.pending',
        'audience' => 'gestor',
        'title' => 'Fila de Redações e Correções Manuais',
        'slug' => 'fila-de-redacoes-e-correcoes-manuais',
        'category' => 'Avaliações',
        'content' => <<<'MARKDOWN'
## Para que serve
Centralizar todas as respostas dissertativas e redações enviadas pelos estudantes que estão pendentes de avaliação e atribuição de nota por um professor ou gestor.

## Passo a passo
1. Analise a fila de tentativas com status "Aguardando Correção Manual", identificando o curso, aluno, lição e data de envio.
2. Clique em "Corrigir Tentativa" na linha do aluno para abrir o ambiente de avaliação detalhada.
3. O contador numérico no topo (e no menu lateral) indica a quantidade total de redações pendentes de análise na organização.

## Regras e limites
- Professores vinculados a um curso específico visualizam apenas as redações das suas turmas.
- Gestores da organização têm visão ampla de todas as redações pendentes do seu tenant.
- Enquanto uma tentativa permanecer na fila pendente, o aluno não tem sua nota consolidada e não pode gerar certificado caso a aprovação dependa deste quiz.

## Dúvidas comuns
**Mais de um professor pode corrigir a mesma tentativa?**
A tentativa pode ser aberta por qualquer docente habilitado na turma, mas a primeira submissão de nota finaliza a pendência na fila.

**O aluno recebe aviso quando a redação for corrigida?**
Sim, o aluno pode consultar a nota atualizada e o feedback na tela de resultados da avaliação.
MARKDOWN
    ],
    [
        'target_page_key' => 'quiz-attempts.show',
        'audience' => 'gestor',
        'title' => 'Correção de Tentativa Dissertativa',
        'slug' => 'correcao-de-tentativa-dissertativa',
        'category' => 'Avaliações',
        'content' => <<<'MARKDOWN'
## Para que serve
Examinar o texto redigido pelo estudante, atribuir pontuação individual para cada questão dissertativa e fornecer comentários pedagógicos (feedback) antes de consolidar a nota final da tentativa.

## Passo a passo
1. Leia o enunciado da questão dissertativa e a resposta textual submetida pelo aluno.
2. No campo "Pontuação", atribua a nota obtida de acordo com o peso da questão.
3. No campo "Feedback / Comentários", redija as orientações pedagógicas justificando a nota.
4. Repita o processo para todas as questões dissertativas pendentes daquela tentativa.
5. Clique em "Consolidar Correção" para registrar a avaliação no sistema.

## Regras e limites
- Todas as questões dissertativas da tentativa devem ser avaliadas para que o status mude para "Corrigida" (`graded`).
- Ao salvar a correção, o motor do sistema recalcula imediatamente a nota percentual final da tentativa (`score_percentage`), somando as questões automáticas e manuais sobre o valor total da prova.
- Se o aluno atingir a nota mínima exigida pelo curso e cumprir as demais regras, a aprovação é registrada e o certificado liberado.

## Dúvidas comuns
**Posso alterar a nota após ter consolidado a correção?**
Sim, o gestor ou professor com permissão pode reabrir a tentativa e ajustar as notas e comentários.

**A nota corrigida substitui automaticamente a nota anterior do aluno?**
O sistema armazena todas as tentativas individualmente e considera o valor mais alto (`MAX`) entre as tentativas aprovadas para os requisitos do curso.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.completion-rules.index',
        'audience' => 'gestor',
        'title' => 'Regras de Conclusão do Curso',
        'slug' => 'regras-de-conclusao-do-curso',
        'category' => 'Certificados',
        'content' => <<<'MARKDOWN'
## Para que serve
Definir os critérios obrigatórios que o estudante deve atingir para ser considerado concluído no curso e obter o direito à emissão do certificado digital.

## Passo a passo
1. Visualize as regras atualmente ativas no curso na tabela de critérios.
2. Clique em "Nova Regra de Conclusão" para configurar um novo requisito.
3. Escolha entre os tipos de regras disponíveis:
   - **Todas as Lições Concluídas**: exige que 100% das aulas e materiais tenham sido marcados como concluídos.
   - **Progresso Mínimo Geral**: estabelece uma porcentagem mínima da carga horária ou lições (ex.: 80%).
   - **Aprovação em Quiz Específico**: exige nota mínima em uma avaliação obrigatória.
4. Salve as regras configuradas para o curso.

## Regras e limites
- O motor de elegibilidade de certificados (`IssueCertificateAction`) opera sob um **"E" lógico (AND)** estrito entre todas as regras cadastradas: o aluno deve cumprir TODAS as regras ativas simultaneamente para concluir o curso.
- Cursos sem regras de conclusão cadastradas não emitem certificados automaticamente.
- A alteração de regras afeta as futuras conclusões, preservando os certificados já emitidos anteriormente.

## Dúvidas comuns
**O que acontece se eu cadastrar duas regras de quiz diferentes?**
O aluno precisará ser aprovado em ambos os quizzes com as respectivas notas mínimas para poder concluir o treinamento.

**O certificado é emitido na hora em que a última regra for cumprida?**
Sim. A validação é síncrona: assim que o último marco é concluído, o certificado é gerado com seu hash criptográfico e disponibilizado imediatamente.
MARKDOWN
    ],
    [
        'target_page_key' => 'courses.certificates.index',
        'audience' => 'gestor',
        'title' => 'Certificados Emitidos e Revogação',
        'slug' => 'certificados-emitidos-e-revogacao',
        'category' => 'Certificados',
        'content' => <<<'MARKDOWN'
## Para que serve
Gerenciar todo o histórico de certificados emitidos para os alunos do curso, realizar o download das cópias em PDF e efetuar a revogação de certificados inválidos ou emitidos indevidamente.

## Passo a passo
1. Consulte a lista de certificados com nome do aluno, código validador (hash SHA-256), data de emissão e status atual (Ativo ou Revogado).
2. Clique em "Baixar PDF" para gerar e visualizar o documento oficial com layout institucional e QR Code de autenticação.
3. Para anular um documento, clique em "Revogar Certificado" e informe a justificativa formal obrigatória no formulário de confirmação.

## Regras e limites
- A revogação de um certificado é **lógica, permanente e terminal**: o registro nunca é excluído fisicamente do banco de dados para preservar a trilha de auditoria.
- A página pública de verificação (`/validar-certificado/{hash}`) nunca dá erro 404 para um certificado revogado: ela informa expressamente que o documento foi revogado, a data do cancelamento e o motivo.
- O hash validador SHA-256 é imutável e gerado no momento da emissão a partir dos dados do curso, aluno e data.

## Dúvidas comuns
**É possível reativar um certificado que foi revogado por engano?**
Não. Por diretrizes de conformidade jurídica e segurança documental, um certificado revogado permanece revogado. Caso o aluno tenha direito ao certificado, deve ser emitida uma nova via com novo hash.

**Como terceiros verificam a autenticidade do certificado?**
Qualquer pessoa pode ler o QR Code impresso no documento ou acessar o endereço `/validar-certificado` e digitar o código hash para verificar a autenticidade diretamente no sistema.
MARKDOWN
    ],
    [
        'target_page_key' => 'forum-moderation.index',
        'audience' => 'gestor',
        'title' => 'Moderação do Fórum de Discussão',
        'slug' => 'moderacao-do-forum-de-discussao',
        'category' => 'Fórum',
        'content' => <<<'MARKDOWN'
## Para que serve
Monitorar a conduta da comunidade nos fóruns dos cursos, analisar publicações denunciadas por outros usuários e aplicar ações corretivas, como descarte da denúncia ou remoção definitiva do conteúdo impróprio.

## Passo a passo
1. Acesse a lista de denúncias pendentes de moderação, contendo o conteúdo denunciado (tópico ou resposta), autor da postagem, denunciante e motivo relatado.
2. Avalie o conteúdo da mensagem em conformidade com as diretrizes da sua organização.
3. Se a denúncia for improcedente, clique em "Dispensar Denúncia" para arquivar o alerta e manter a mensagem visível.
4. Se o conteúdo violar as diretrizes, clique em "Remover Conteúdo" para ocultar a publicação da comunidade.

## Regras e limites
- A remoção de um tópico ou resposta pelo moderador marca a publicação como removida, exibindo um aviso de moderação no lugar da mensagem original para manter a coerência das respostas dependentes.
- Gestores e Administradores possuem permissão de remoção direta mesmo sem denúncia prévia.
- Professores podem moderar denúncias relativas aos cursos nos quais atuam como docentes.

## Dúvidas comuns
**O usuário denunciante é identificado publicamente no fórum?**
Não. As denúncias são estritamente confidenciais e visíveis apenas para os membros da equipe de moderação, gestores e administradores.

**O que acontece quando uma denúncia é dispensada?**
O alerta é removido da fila de moderação e o tópico ou resposta continua disponível normalmente no fórum para todos os alunos.
MARKDOWN
    ],
];
