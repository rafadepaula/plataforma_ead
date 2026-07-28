# **Documento de Especificação Técnica, Requisitos e Casos de Uso: Plataforma EAD**

## **1\. Análise de Escopo e Visão Geral do Sistema**

O objetivo deste projeto é o desenvolvimento de uma plataforma de **Ensino a Distância (EAD)** enxuta e especializada para a capacitação continuada de eletricistas associados ao conselho. O projeto responde a uma restrição orçamentária decorrente do ano de revisão tarifária, exigindo uma solução de **baixo custo de desenvolvimento e custo contínuo próximo a zero**, sem abdicar do controle rígido de acesso, acompanhamento do progresso pedagógico, avaliação de conhecimento interativa, ambiente de interação/discussão, suporte contínuo ao usuário e emissão automatizada de certificados com autenticidade verificável.

A aplicação contará com uma **Landing Page pública (página inicial index sem necessidade de login)** que apresenta a plataforma, seus benefícios institucionais e disponibiliza acesso direto à **Central de Ajuda**, além dos pontos de entrada de autenticação e auto-cadastro via convite. A **Central de Ajuda** cobrirá obrigatoriamente 100% das telas e rotas operacionais do sistema, fornecendo suporte contextual para alunos e administradores.

A segurança e isolamento de conteúdo são estruturados por um motor rígido de **Matrícula e Controle de Acesso**: um aluno **só possui visibilidade e permissão de acesso aos cursos nos quais estiver expressamente matriculado**. A matrícula ocorre por vinculação direta realizada pelo Administrador ou através de **Links de Convite (invitation\_links)** associados a um curso específico. O fluxo de convite é inteligente: se o usuário já possuir cadastro no sistema, o link não exige a criação de uma nova conta, mas sim realiza a autenticação do usuário e o vincula imediatamente ao novo curso.

Para estimular a troca de conhecimentos e o suporte pedagógico, a plataforma dispõe do **Fórum de Discussão** integrado a cada curso. Nesse ambiente, alunos matriculados e professores/administradores podem abrir tópicos de dúvidas e publicar respostas, mantendo todo o histórico de interações isolado e protegido por curso.

A infraestrutura será implantada em um ambiente de **hospedagem compartilhada** (suporte a PHP 8.2+ e MySQL/MariaDB), descartando custos fixos com servidores dedicados ou VPS. O streaming de vídeo utilizará a infraestrutura do **YouTube (vídeos não-listados com player incorporado)**. Materiais complementares em texto, imagem e PDF, além do motor de **Questionários e Avaliações (múltipla escolha, resposta única e dissertativa)**, serão servidos diretamente pela aplicação.

A emissão do certificado será vinculada a um motor flexível de **Pré-requisitos e Regras de Liberação**, permitindo ao Administrador definir pontuações mínimas (ex: 70% em questionários) e percentual de conclusão de atividades (ex: 100% dos vídeos assistidos).

Para garantir a qualidade, estabilidade e manutenibilidade do código em hospedagem compartilhada, o projeto adota **Guardrails e Suíte de Testes Automatizados (Unitários, Integração e E2E)** com meta obrigatória de **cobertura mínima de código de 95%** em todos os módulos.

## **2\. Arquitetura Técnica, Modelo de Dados e Infraestrutura**

### **2.1. Arquitetura da Aplicação**

* **Backend:** **Laravel (versão mais recente)** arquitetado no padrão MVC (Model-View-Controller) clássico, utilizando formulários de validação (Form Requests), autenticação nativa via sessões e cookies sanitizados do Laravel (web guard com suporte a CSRF), Laravel Gates/Policies para autorização de acesso a cursos e fóruns, e ORM Eloquent para manipulação do banco de dados.  
* **Frontend:** **Laravel Blade \+ JavaScript Puro (ES6+) e jQuery**.  
  * A renderização visual das telas é feita diretamente no servidor através de Blade Templates.  
  * O **jQuery** e **JavaScript vanilla** são utilizados para dinamismo na interface, envio de requisições assíncronas via AJAX ($.ajax / fetch), manipulação do DOM, controle do player de vídeo, modais, execução do player de questionários interativos e atualização em tempo real do Fórum de Discussão.  
  * Estilização responsiva (*Mobile-First*) com CSS3 e Bootstrap 5 / Tailwind CSS via CDN ou compilação simples de assets.  
* **Estratégia de Deploy e Testes (CI/CD Guardrails):**  
  * O projeto Laravel é implantado no diretório da hospedagem compartilhada com o diretório público mapeado para public\_html ou public.  
  * **Suíte de Testes:** Execução via PHPUnit / Pest PHP para testes Unitários e de Integração backend, combinados com **Laravel Dusk ou Cypress/Playwright** para testes de ponta a ponta (E2E).  
  * **Guardrail de Cobertura:** Pipeline de integração contínua (CI) bloqueia automações de deploy caso a cobertura de código (*code coverage*) fique abaixo de **95%** ou haja qualquer falha em edge cases (ex: tentativa de acesso não autorizado a curso não matriculado).  
* **Banco de Dados:** MySQL 8.0 / MariaDB 10.4+.  
* **Processamento de PDFs:** Pacote Laravel interno (barryvdh/laravel-dompdf ou spatie/laravel-pdf) para geração dinâmica dos certificados em PDF.

### **2.2. Modelo de Banco de Dados (Esquema Relacional)**

1. **users**  
   * id (PK, BigInt, Auto-increment)  
   * name (VarChar 150\)  
   * email (VarChar 150, Unique)  
   * cpf (VarChar 14, Unique, Nullable)  
   * password (VarChar 255 \- Hash bcrypt)  
   * role (Enum: 'admin', 'student')  
   * status (Enum: 'active', 'inactive')  
   * created\_at, updated\_at (Timestamp)  
2. **invitation\_links**  
   * id (PK, BigInt, Auto-increment)  
   * token (VarChar 64, Unique)  
   * course\_id (FK \-\> courses.id, Cascade Delete) — *Curso ao qual o convite se vincula*  
   * max\_uses (Int, Default: 0\) — *0 para ilimitado*  
   * current\_uses (Int, Default: 0\)  
   * expires\_at (DateTime, Nullable)  
   * created\_by (FK \-\> users.id)  
   * created\_at (Timestamp)  
3. **courses**  
   * id (PK, BigInt, Auto-increment)  
   * title (VarChar 200\)  
   * description (Text)  
   * workload\_hours (Int)  
   * is\_published (Boolean, Default: false)  
   * created\_at, updated\_at (Timestamp)  
4. **course\_user (Enrollments / Matrículas)**  
   * id (PK, BigInt, Auto-increment)  
   * user\_id (FK \-\> users.id, Cascade Delete)  
   * course\_id (FK \-\> courses.id, Cascade Delete)  
   * enrolled\_at (DateTime)  
   * status (Enum: 'active', 'cancelled', 'completed')  
   * created\_at, updated\_at (Timestamp)  
   * UNIQUE KEY (user\_id, course\_id)  
5. **modules**  
   * id (PK, BigInt, Auto-increment)  
   * course\_id (FK \-\> courses.id, Cascade Delete)  
   * title (VarChar 200\)  
   * description (Text, Nullable)  
   * order\_index (Int, Default: 0\)  
   * created\_at, updated\_at (Timestamp)  
6. **lessons**  
   * id (PK, BigInt, Auto-increment)  
   * module\_id (FK \-\> modules.id, Cascade Delete)  
   * title (VarChar 200\)  
   * type (Enum: 'content', 'quiz')  
   * content\_text (LongText, Nullable)  
   * youtube\_url (VarChar 255, Nullable)  
   * pdf\_path (VarChar 255, Nullable)  
   * image\_path (VarChar 255, Nullable)  
   * order\_index (Int, Default: 0\)  
   * is\_published (Boolean, Default: true)  
   * created\_at, updated\_at (Timestamp)  
7. **quizzes**  
   * id (PK, BigInt, Auto-increment)  
   * lesson\_id (FK \-\> lessons.id, Cascade Delete)  
   * title (VarChar 200\)  
   * instructions (Text, Nullable)  
   * allow\_retries (Boolean, Default: true)  
   * show\_correct\_answers (Boolean, Default: true)  
   * min\_score\_percentage (Decimal 5,2, Default: 70.00)  
   * created\_at, updated\_at (Timestamp)  
8. **quiz\_questions**  
   * id (PK, BigInt, Auto-increment)  
   * quiz\_id (FK \-\> quizzes.id, Cascade Delete)  
   * question\_text (Text)  
   * type (Enum: 'single\_choice', 'multiple\_choice', 'essay')  
   * order\_index (Int, Default: 0\)  
   * created\_at, updated\_at (Timestamp)  
9. **quiz\_options**  
   * id (PK, BigInt, Auto-increment)  
   * question\_id (FK \-\> quiz\_questions.id, Cascade Delete)  
   * option\_text (Text)  
   * is\_correct (Boolean, Default: false)  
   * created\_at, updated\_at (Timestamp)  
10. **quiz\_attempts**  
    * id (PK, BigInt, Auto-increment)  
    * quiz\_id (FK \-\> quizzes.id, Cascade Delete)  
    * user\_id (FK \-\> users.id, Cascade Delete)  
    * score\_percentage (Decimal 5,2)  
    * is\_passed (Boolean, Default: false)  
    * completed\_at (DateTime)  
11. **quiz\_answers**  
    * id (PK, BigInt, Auto-increment)  
    * attempt\_id (FK \-\> quiz\_attempts.id, Cascade Delete)  
    * question\_id (FK \-\> quiz\_questions.id, Cascade Delete)  
    * selected\_option\_ids (Json, Nullable)  
    * essay\_answer (Text, Nullable)  
    * is\_correct (Boolean, Default: false)  
12. **course\_completion\_rules**  
    * id (PK, BigInt, Auto-increment)  
    * course\_id (FK \-\> courses.id, Cascade Delete)  
    * rule\_type (Enum: 'content\_percentage', 'quiz\_score', 'specific\_lesson')  
    * target\_id (BigInt, Nullable)  
    * required\_percentage (Decimal 5,2)  
    * created\_at, updated\_at (Timestamp)  
13. **lesson\_progress**  
    * id (PK, BigInt, Auto-increment)  
    * user\_id (FK \-\> users.id, Cascade Delete)  
    * lesson\_id (FK \-\> lessons.id, Cascade Delete)  
    * is\_completed (Boolean, Default: false)  
    * completed\_at (DateTime, Nullable)  
    * UNIQUE KEY (user\_id, lesson\_id)  
14. **forum\_topics**  
    * id (PK, BigInt, Auto-increment)  
    * course\_id (FK \-\> courses.id, Cascade Delete)  
    * user\_id (FK \-\> users.id, Cascade Delete) — *Autor do tópico*  
    * title (VarChar 200\)  
    * content (Text)  
    * is\_pinned (Boolean, Default: false)  
    * created\_at, updated\_at (Timestamp)  
15. **forum\_replies**  
    * id (PK, BigInt, Auto-increment)  
    * topic\_id (FK \-\> forum\_topics.id, Cascade Delete)  
    * user\_id (FK \-\> users.id, Cascade Delete) — *Autor da resposta*  
    * content (Text)  
    * created\_at, updated\_at (Timestamp)  
16. **certificates**  
    * id (PK, BigInt, Auto-increment)  
    * user\_id (FK \-\> users.id)  
    * course\_id (FK \-\> courses.id)  
    * validation\_hash (VarChar 64, Unique)  
    * issued\_at (DateTime)  
    * UNIQUE KEY (user\_id, course\_id)  
17. **help\_articles**  
    * id (PK, BigInt, Auto-increment)  
    * title (VarChar 200\)  
    * slug (VarChar 200, Unique)  
    * category (Enum: 'aluno', 'admin', 'geral')  
    * target\_page\_key (VarChar 100\)  
    * content (LongText)  
    * created\_at, updated\_at (Timestamp)  
18. **system\_settings**  
    * setting\_key (VarChar 50, PK)  
    * setting\_value (Text)

### **2.3. Diretrizes de Segurança e Otimização para Hospedagem Compartilhada**

* **Isolamento via Middleware e Policies:** Todas as rotas de cursos, aulas, downloads e fóruns utilizam um Middleware específico (EnsureStudentIsEnrolled) e Laravel Policies para validar a existência de registro ativo na tabela course\_user.  
* **Proteção de Diretórios:** O diretório de uploads (storage/app/public) contém .htaccess bloqueando navegação de diretórios (Options \-Indexes) e execução de PHP.  
* **Proteção contra CSRF e XSS:** Token CSRF obrigatório em formulários e requisições AJAX ($.ajax), acompanhado da sanitização de entradas HTML de questionários e Fórum de Discussão.  
* **Embed do YouTube Sanitizado:** Utilização de parâmetros restritivos na URL do iframe: ?modestbranding=1\&rel=0\&controls=1\&disablekb=1.

## **3\. Matriz de Requisitos Funcionais (RF)**

| ID | Módulo | Descrição do Requisito |
| :---- | :---- | :---- |
| **RF01** | Autenticação | Permitir login de Administradores e Alunos via e-mail e senha com hash bcrypt. |
| **RF02** | Recuperação de Senha | Enviar e-mail de redefinição de senha com token temporário de uso único via SMTP. |
| **RF03** | Auto-cadastro e Matrícula por Convite | Permitir auto-cadastro de novos alunos e matrícula de alunos já existentes via link temporário vinculado a um curso específico. |
| **RF04** | Gestão de Alunos | Permitir ao Admin cadastrar, listar, buscar, editar e inativar/ativar alunos via Blade \+ jQuery. |
| **RF05** | Importação em Lote | Permitir importação de múltiplos alunos via upload de arquivo CSV com processamento assíncrono. |
| **RF06** | Gestão de Cursos e Módulos | Permitir ao Admin criar, editar, reordenar e excluir Cursos e Módulos. |
| **RF07** | Conteúdo Multimídia | Permitir ao Admin cadastrar aulas com Texto (Rich Text), Imagem, PDF e Vídeo (YouTube). |
| **RF08** | Gestão de Questionários | Permitir ao Admin criar questionários contendo enunciado, opções, tipo (única, múltipla escolha ou dissertativa), resposta correta, flag de refaça e exibição de gabarito. |
| **RF09** | Execução de Questionários | Exibir o questionário para o aluno, calcular o percentual final de acertos e apresentar o resultado e gabarito. |
| **RF10** | Regras de Certificado | Permitir ao Admin configurar pré-requisitos customizados para emissão do certificado por curso (% de aulas e % em questionários). |
| **RF11** | Landing Page Pública | Página inicial pública (/) apresentando o sistema, objetivos institucionais e atalhos para login/ajuda. |
| **RF12** | Central de Ajuda Integral | Disponibilizar área de ajuda cobrindo 100% das páginas/funcionalidades, com links contextuais presentes em todas as telas. |
| **RF13** | Player de Vídeo Responsivo | Renderizar player do YouTube incorporado com controles restritos na área de estudos. |
| **RF14** | Leitor de PDF Integrado | Exibir PDFs diretamente na página da aula com visualizador HTML/iframe e botão de download. |
| **RF15** | Registro de Progresso | Registrar a conclusão das lições e recalcular o progresso global em tempo real via AJAX. |
| **RF16** | Emissão de Certificado | Gerar arquivo PDF do certificado quando todas as regras e pré-requisitos do curso forem cumpridos. |
| **RF17** | Validação de Certificado | Consultar autenticidade pública do certificado via código Hash SHA-256 ou QR Code. |
| **RF18** | Dashboard e Relatórios | Exibir métricas gerenciais e permitir a exportação de dados detalhados em CSV. |
| **RF19** | Suíte de Testes e Guardrails | Cobertura mínima obrigatória de **95%** de testes automatizados (Unitários, Integração e E2E) para todos os módulos. |
| **RF20** | Controle Rígido de Acesso por Matrícula | Garantir que o aluno visualize e acesse **apenas** os cursos em que possui vínculo ativo em course\_user. |
| **RF21** | Gestão de Matrículas pelo Admin | Permitir ao Administrador vincular ou remover manualmente alunos em um ou mais cursos. |
| **RF22** | Fórum de Discussão do Curso | Disponibilizar área interativa por curso onde alunos matriculados e professores/admins podem criar tópicos e responder dúvidas. |

## **4\. Matriz de Requisitos Não-Funcionais (RNF)**

| ID | Categoria | Descrição do Requisito |
| :---- | :---- | :---- |
| **RNF01** | Stack & Compatibilidade | Backend em Laravel (última versão) com Blade, JS puro e jQuery. Compatível 100% com hospedagem compartilhada (PHP 8.2+ e MySQL 8/MariaDB). |
| **RNF02** | Ausência de Daemons | Não depender de filas persistentes pesadas (utilizar driver sync ou database leve para e-mails) ou WebSockets. |
| **RNF03** | Testabilidade e Guardrails | Cobertura de testes de no mínimo **95%** auditada por ferramentas de coverage (Pest/PHPUnit/Dusk/Cypress) cobrindo happy paths e edge cases. |
| **RNF04** | Responsividade | Layout construído em padrão *Mobile-First* compatível com smartphones, tablets e desktops. |
| **RNF05** | Sanitização e Segurança | Proteção contra XSS em tópicos/respostas do Fórum, CSRF nativo e bloqueio de autorização via Gateways de Matrícula. |

## **5\. Regras de Negócio (RN)**

| ID | Descrição da Regra |
| :---- | :---- |
| **RN01** | **Liberação do Certificado:** O certificado só é liberado para geração se todas as **regras de pré-requisitos do curso** forem atendidas (ex: 100% dos conteúdos consumidos E nota ![][image1] % mínima exigida no questionário). |
| **RN02** | **Cálculo da Nota do Questionário:** A porcentagem final do questionário é calculada dividindo o total de pontos/questões corretas pelo número total de questões da prova, multiplicado por 100\. |
| **RN03** | **Repetição de Questionários:** Se allow\_retries for false, o aluno só submete uma única vez. Se true, novas tentativas são permitidas mantendo o histórico. |
| **RN04** | **Exibição do Gabarito:** O gabarito só é exibido na tela final se show\_correct\_answers estiver marcado como true. |
| **RN05** | **Cobertura de Ajuda 100%:** Todas as páginas operacionais exibem atalho direcionando para a página correspondente da Central de Ajuda (/ajuda/{target\_page\_key}). |
| **RN06** | **Guardrail de Cobertura de Testes (95%):** Nenhuma alteração pode ser implantada se a cobertura de testes for inferior a 95% ou se qualquer teste falhar. |
| **RN07** | **Imutabilidade do Certificado:** O código hash é gerado via SHA-256 combinando user\_id, course\_id, issued\_at e a chave da aplicação (APP\_KEY). |
| **RN08** | **Restrição Estrita de Matrícula:** Alunos sem registro em course\_user para o curso solicitado recebem erro 403 (Forbidden) ao tentar acessar conteúdos, aulas ou Fórum do curso. |
| **RN09** | **Fluxo Inteligente de Convite:** Se o e-mail informado ao acessar um invitation\_link já pertencer a uma conta existente, o sistema exige a senha da conta e, após validação, **efetua a matrícula diretamente no curso** sem criar duplicidade de usuário. |
| **RN10** | **Isolamento das Discussões do Fórum:** Apenas alunos matriculados no curso e administradores podem criar tópicos, ler discussões ou publicar respostas no Fórum de Discussão do curso específico. |

## **6\. Mapeamento Completo de Casos de Uso (UC)**

### **UC01: Autenticar, Encerrar Sessão e Recuperar Senha**

* **Atores:** Aluno, Administrador.  
* **Descrição:** Autenticar o usuário no sistema com controle de permissões por perfil (Admin vs. Aluno).  
* **Fluxo Principal:** Usuário insere e-mail e senha. Backend valida hash bcrypt e redireciona o Admin para o Dashboard ou o Aluno para "Meus Cursos Matriculados".

### **UC02: Gerenciar Perfil do Usuário**

* **Atores:** Aluno, Administrador.  
* **Descrição:** Permitir alteração de nome, e-mail e senha pessoal.

### **UC03: Gestão de Alunos e Matrículas pelo Administrador**

* **Atores:** Administrador.  
* **Descrição:** Cadastrar, listar, editar, inativar alunos e matricular/desmatricular alunos manualmente em cursos específicos.  
* **Fluxo Principal:** Admin acessa "Alunos" ou "Gestão de Matrículas", seleciona o aluno e marca/desmarca os cursos aos quais ele possui acesso.

### **UC04: Auto-cadastro e Matrícula via Link Temporário (Usuários Novos e Existentes)**

* **Atores:** Eletricista / Aluno (Novo ou Existente).  
* **Descrição:** Permitir a entrada no curso através de link de convite (/convite/{token}).  
* **Pré-condições:** Link de convite válido gerado e vinculado a um course\_id.

#### **Fluxo Principal (Novo Usuário):**

1. O usuário acessa a URL do convite.  
2. O sistema valida a expiração e o limite de usos (current\_uses \< max\_uses).  
3. O formulário solicita: Nome, CPF, E-mail e Senha.  
4. O sistema cria o registro em users e a matrícula em course\_user vinculada ao curso do convite.  
5. O usuário é logado automaticamente e redirecionado para a aula inicial do curso.

#### **Fluxo Alternativo 01 (Usuário Já Possui Conta no Sistema):**

1. O usuário acessa a URL do convite e informa seu e-mail.  
2. O backend identifica que o e-mail já existe na tabela users.  
3. O formulário se adapta exibindo a mensagem: "Identificamos seu cadastro\! Digite sua senha para se matricular neste novo curso".  
4. O usuário insere a senha. O backend valida a credencial.  
5. O backend registra a nova matrícula na tabela course\_user para aquele course\_id (se ainda não matriculado).  
6. O usuário é redirecionado para a área de estudos do novo curso sem duplicar sua conta.

### **UC05: Gerenciamento de Cursos e Módulos**

* **Atores:** Administrador.  
* **Descrição:** CRUD de cursos e módulos com ordenação visual.

### **UC06: Gerenciamento de Lições Multimídia**

* **Atores:** Administrador.  
* **Descrição:** Cadastro de aulas com Rich Text, upload de imagens/PDFs sanitizados e embed de vídeo do YouTube.

### **UC07: Consumo de Aulas e Navegação Restrita por Matrícula**

* **Atores:** Aluno.  
* **Descrição:** Visualizar a estrutura do curso e assistir às aulas.  
* **Pré-condições:** Aluno autenticado e **matriculado no curso** (course\_user).

#### **Fluxo Principal:**

1. Aluno acessa "Meus Cursos" e clica em um curso no qual está matriculado.  
2. O Middleware EnsureStudentIsEnrolled valida o vínculo.  
3. A interface Blade renderiza o player de vídeo, o leitor de PDF e a sidebar com a lista de módulos/aulas.

#### **Fluxo de Exceção (Tentativa de Acesso Não Matriculado):**

1. O aluno tenta acessar diretamente a URL /aluno/cursos/99 sem estar matriculado.  
2. O Middleware bloqueia a requisição, nega o acesso (HTTP 403\) e redireciona o aluno para a listagem "Meus Cursos" com alerta: "Você não possui matrícula neste curso".

### **UC08: Registro e Rastreamento do Progresso do Aluno**

* **Atores:** Aluno.  
* **Descrição:** Registrar a conclusão de lições e recalcular a porcentagem global do curso via AJAX.

### **UC09: Emissão e Download do Certificado**

* **Atores:** Aluno, Administrador.  
* **Descrição:** Gerar o certificado em PDF após validação dos pré-requisitos (UC15).

### **UC10: Validação Pública de Certificados**

* **Atores:** Público Geral, Conselho.  
* **Descrição:** Consultar a autenticidade do certificado via código SHA-256 ou QR Code na página pública.

### **UC11: Painel Gerencial, Dashboard e Relatórios**

* **Atores:** Administrador.  
* **Descrição:** Métricas de matrículas, conclusões, notas de questionários e exportação CSV.

### **UC12: Configurações Gerais do Sistema e Personalização do Certificado**

* **Atores:** Administrador.  
* **Descrição:** Configurar parâmetros SMTP, logos, assinaturas e chaves de validação.

### **UC13: Landing Page Pública e Central de Ajuda Integral**

* **Atores:** Visitante, Aluno, Administrador.  
* **Descrição:** Página inicial pública (/) e Central de Ajuda com cobertura de 100% das telas da aplicação.

### **UC14: Gestão e Aplicação de Questionários e Avaliações**

* **Atores:** Administrador, Aluno.  
* **Descrição:** Cadastro pelo Admin de provas objetivas/dissertativas e realização pelo aluno com cálculo do percentual final de acertos.

### **UC15: Configuração de Pré-requisitos para Emissão de Certificado**

* **Atores:** Administrador.  
* **Descrição:** Definição de regras de conclusão (% de aulas assistidas e % em questionários) para liberar a geração do certificado.

### **UC16: Suíte de Testes Automatizados e Guardrails de Qualidade (95%+ Coverage)**

* **Atores:** Desenvolvedor, Sistema de CI/CD.  
* **Descrição:** Execução automatizada de testes Unitários, Integração e E2E garantindo no mínimo 95% de cobertura antes do deploy.

### **UC17: Fórum de Discussão do Curso**

* **Atores:** Aluno Matriculado, Administrador.  
* **Descrição:** Espaço interativo por curso para criação de tópicos de dúvida, troca de experiências e publicação de respostas.  
* **Pré-condições:** Usuário autenticado e **matriculado no curso** correspondente.

#### **Fluxo Principal (Criar Tópico):**

1. O aluno acessa a aba "Fórum de Discussão" dentro do curso em que está matriculado (/aluno/cursos/{id}/forum).  
2. O sistema valida a matrícula via Middleware e renderiza a lista de tópicos existentes.  
3. O aluno clica em "Novo Tópico", preenche o Título e o Conteúdo da dúvida.  
4. Clica em "Publicar Tópico".  
5. O Laravel sanitiza o texto contra XSS, salva em forum\_topics e atualiza a listagem via AJAX.

#### **Fluxo Principal (Responder Tópico):**

1. Qualquer aluno matriculado ou Admin clica em um tópico aberto.  
2. O sistema exibe o histórico de mensagens e o campo de resposta.  
3. O usuário digita a resposta e clica em "Enviar Resposta".  
4. O sistema grava a mensagem em forum\_replies e atualiza a discussão em tempo real na tela.

#### **Fluxo de Exceção (Acesso Negado ao Fórum):**

1. Um aluno não matriculado tenta acessar a URL do fórum do curso /aluno/cursos/5/forum.  
2. O sistema bloqueia a requisição (HTTP 403 \- Forbidden) informando que o fórum é exclusivo para alunos matriculados no curso.

## **7\. Matriz de Rastreabilidade (Requisitos vs. Casos de Uso)**

| Requisito Funcional | Caso de Uso Associado |
| :---- | :---- |
| **RF01**, **RF02** | **UC01**, **UC02** |
| **RF03** | **UC04** |
| **RF04**, **RF05** | **UC03** |
| **RF06** | **UC05** |
| **RF07** | **UC06** |
| **RF08**, **RF09** | **UC14** |
| **RF10** | **UC15** |
| **RF11**, **RF12** | **UC13** |
| **RF13**, **RF14** | **UC07** |
| **RF15** | **UC08** |
| **RF16** | **UC09**, **UC15** |
| **RF17** | **UC10** |
| **RF18** | **UC11** |
| **RF19** | **UC16** |
| **RF20** | **UC07**, **UC17** |
| **RF21** | **UC03**, **UC04** |
| **RF22** | **UC17** |

## 

## **8\. Design System, Especificação Visual e Diretrizes de UI/UX**

### **9.1. Guia de Fundamentos Visuais (Design Tokens)**

* **Azul Elétrico Principal (Primary Brand):** \#004080.  
* **Amarelo/Dourado de Destaque (Secondary Accent):** \#F7D700 ou \#EAB308.  
* **Fundo de Tela (Background Neutral):** \#F8FAFC.  
* **Superfície/Cards (Surface Neutral):** \#FFFFFF.  
* **Texto Primário:** \#1E293B | **Texto Secundário:** \#64748B.  
* **Sucesso (Success Green):** \#10B981 | **Erro/Perigo (Danger Red):** \#EF4444.

### **9.2. Especificação Visual das Novas Telas**

#### **Landing Page Pública (/ \- UC13)**

* **Header:** Logo do Conselho, links "Sobre a Capacitação", "Central de Ajuda" e botão "Entrar na Plataforma".  
* **Hero Section:** Banner principal com imagem de eletricistas, título institucional e botão "Acessar Cursos".  
* **Footer:** Links institucionais e atalho para a Central de Ajuda.

#### **Área de Meus Cursos Matriculados (Aluno \- UC07)**

* **Grid de Cursos:** Cards exibindo apenas os cursos em que o aluno possui registro em course\_user.  
* **Indicador:** Barra de progresso percentual individual por curso e botão "Continuar Estudando".

#### **Fórum de Discussão do Curso (/aluno/cursos/{id}/forum \- UC17)**

* **Header do Fórum:** Nome do Curso \+ Botão destacado "+ Criar Novo Tópico".  
* **Lista de Tópicos:** Cards contendo Título do Tópico, Nome do Autor (badge indicando se é Aluno ou Professor), Data e Contador de Respostas.  
* **Visualização da Discussão:** Mensagem original no topo com avatar e estilo destacado \+ linha do tempo de respostas formatadas abaixo com campo para rápida resposta no rodapé.

#### **Formulário de Convite Inteligente (/convite/{token} \- UC04)**

* **Design Adaptativo:** Ao digitar o e-mail, se for um novo aluno, exibe campos completos de cadastro. Se for usuário existente, oculta campos e solicita apenas a confirmação de senha para vinculação ao novo curso.

[image1]: <data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAA4AAAAXCAYAAAA7kX6CAAAAyklEQVR4XmNgGAU0AsrKyrIKCgor5eXlq6WlpYXR5QkCoEZPIN4NxJNkZGSk0eUJAqBGJyDeDMSS6HJEAaDGZXJycu1A24XQ5QgCoN8NgAbMAhkAZEugyxMEQM2aQM07gPRiY2NjVnR5FAB0ogrQloVAxe+BuEldXZ0XXQ0GUFRUVAcqPgfEOUDNAujyGEBJSUkNqHgJUPEmdDmsAOh+JVD8gfwAxIbo8lgB0FlAtfJTgX5SRZcbLgCUO+QhiZsYvFVFRYUd3QzaAwCyQCwBS5P5HAAAAABJRU5ErkJggg==>