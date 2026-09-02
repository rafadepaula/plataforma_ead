# Plataforma EAD Multitenant

A **Plataforma EAD Multitenant** é uma solução completa e escalável de Ensino a Distância (LMS - *Learning Management System*) desenvolvida em **Laravel 13** e **PHP 8.5**. Projetada para atender múltiplas organizações em uma arquitetura **Single-Database Multitenancy**, a plataforma oferece isolamento rigoroso de dados por instituição, gerenciamento completo de cursos e alunos, sala de aula virtual com suporte multimídia, motor de avaliações, emissão de certificados digitais autenticados e relatórios gerenciais.

---

## 🎯 Propósito do Projeto

O objetivo do sistema é fornecer uma infraestrutura de EAD moderna, segura e de alta performance onde **múltiplas organizações (empresas, escolas ou instituições de ensino)** possam gerenciar de forma independente seus cursos, módulos, alunos e emissão de certificados, enquanto os **alunos** utilizam uma única conta para acessar treinamentos e conteúdos de diferentes organizações.

---

## ✨ Principais Funcionalidades

### 🏢 1. Arquitetura Multitenant (Múltiplas Organizações)
- **Isolamento de Dados em Banco Único (Single-Database)**: Segregação automática de dados por organização via `org_id` e escopo global (`OrgScope`).
- **Suporte Multi-Org para Alunos**: O mesmo aluno pode se matricular e realizar cursos em diferentes organizações mantendo um único cadastro unificado.
- **Impersonate Org**: Administradores globais podem alternar de contexto temporariamente para gerenciar o painel de qualquer organização.

### 📚 2. Gestão de Cursos e Conteúdo Multimídia
- **Organização Modular**: Cursos estruturados em Módulos e Lições com reordenação dinâmica via AJAX.
- **Conteúdo Diversificado**: Suporte a lições multimídia incluindo texto rico, imagens, documentos PDF e vídeos integrados via YouTube.
- **Controle de Publicação e Exclusão**: Proteção contra exclusão acidental de cursos que possuem alunos com matrículas ativas.

### 🎓 3. Sala de Aula Virtual & Experiência do Aluno
- **Painel "Meus Cursos"**: Visão centralizada das matrículas do aluno em todas as organizações com progresso percentual em tempo real.
- **Player de Aulas Inteligente**: Detecção automática de tempo assistido em vídeos e marcação de conclusão de aulas.
- **Recálculo Dinâmico de Progresso**: Atualização síncrona do avanço no curso conforme a conclusão das lições.

### 📝 4. Motor de Avaliações & Questionários
- **Diversidade de Questões**: Criação de provas com questões de Escolha Única, Múltipla Escolha, Verdadeiro/Falso e Questões Discursivas (Ensaios).
- **Correção Mista**: Correção automatizada para questões objetivas e painel de correção manual para questões discursivas pelos gestores.
- **Regras Configuráveis**: Definição de limite de tentativas, tempo limite com cronômetro em tempo real, nota mínima para aprovação e liberação condicional de gabarito.

### 📜 5. Emissão e Validação Pública de Certificados
- **Geração Automática de PDF**: Emissão de certificados em PDF com layout customizado e identidade visual (marca/logo) da Organização correspondente.
- **Validação Pública por Hash SHA-256**: Cada certificado gerado possui um código único e QR Code para verificação pública de autenticidade (`/validar-certificado`) sem necessidade de autenticação.

### 🔗 6. Convites Inteligentes & Matrículas Adaptativas
- **Links de Convite por Organização**: URLs personalizadas com controle de validade e limite de usos.
- **Fluxo de Cadastro Adaptativo**: Reconhecimento automático de e-mail existente para matrícula simplificada sem duplicar contas.
- **Importação em Lote**: Painel de matrículas manuais e importação massiva de alunos via arquivo CSV.

### 💬 7. Fórum de Discussão Integrado
- **Comunidade por Curso**: Espaço colaborativo de dúvidas e discussões entre alunos e instrutores, isolado por organização e curso.
- **Segurança & Atualizações**: Sanitização rigorosa contra XSS e atualização de respostas em tempo real via polling AJAX.

### 🔔 8. Notificações & Central de Ajuda Contextual
- **Notificações In-App e por E-mail**: Alertas automáticos para convites, confirmação de matrícula, emissão de certificados e respostas no fórum.
- **Central de Ajuda Integrada**: Botões contextuais de suporte (`<x-help-button />`) cobrindo 100% das telas da aplicação com fallback de artigos.

### 📊 9. Painel Gerencial & Auditoria
- **Dashboard de Analytics**: Métricas de engajamento, matrículas ativas, taxa de conclusão e certificados emitidos por organização.
- **Exportação CSV em Streaming**: Relatórios de grande volume exportados com consumo constante de memória ($O(1)$ RAM).
- **Auditoria do Sistema**: Logs de eventos de autenticação, mutações de dados e ações críticas com mascaramento de dados sensíveis.

---

## 👥 Perfis de Acesso (Roles)

| Perfil | Descrição e Responsabilidades |
|---|---|
| **Admin** | Administrador Global da infraestrutura. Gerencia organizações, configura parâmetros globais do sistema e utiliza o recurso de *Impersonate* para gerenciar qualquer tenant. |
| **Gestor** | Gestor da Organização. Possui controle completo sobre sua instituição: gestão de cursos, módulos, lições, provas, matrículas, relatórios e identidade dos certificados. |
| **Aluno** | Usuário final da plataforma. Acessa seus cursos matriculados, assiste a aulas multimídia, realiza provas, participa dos fóruns e emite seus certificados. |

---

## 🛠️ Stack Tecnológica

- **Backend Framework**: [Laravel 13](https://laravel.com) (PHP 8.5)
- **Banco de Dados**: MySQL 8.0+ / MariaDB 10.4+ (Engine InnoDB, Single-Database Multitenancy via `OrgScope`)
- **Autenticação & Permissões**: `spatie/laravel-permission` (Roles `admin`, `gestor`, `aluno`)
- **Frontend & UI**: Blade Templates (SSR), Bootstrap 5.3, JavaScript (ES6+ / jQuery 3.7+)
- **Gerador de PDF**: `barryvdh/laravel-dompdf`
- **Ambiente de Desenvolvimento**: [Laravel Sail](https://laravel.com/docs/sail) (Docker)
- **Suíte de Testes**: PHPUnit / Pest & [Laravel Dusk](https://laravel.com/docs/dusk) (E2E Browser Testing)

---

## 🚀 Como Executar o Projeto Localmente

O ambiente de desenvolvimento está configurado utilizando **Laravel Sail** (Docker).

### 1. Pré-requisitos
- Docker e Docker Compose instalados.

### 2. Subir os Containers
```bash
vendor/bin/sail up -d
```

### 3. Executar Migrações e Seeds do Banco de Dados
```bash
vendor/bin/sail artisan migrate --seed
```

### 4. Compilar Assets do Frontend
```bash
vendor/bin/sail npm run dev
# ou para build de produção:
vendor/bin/sail npm run build
```

---

## 🧪 Qualidade & Suíte de Testes (TDD)

O projeto foi desenvolvido sob metodologia **Test-Driven Development (TDD)** com alta cobertura de código e testes de navegação End-to-End.

### Executar a Suíte de Testes (PHPUnit)
```bash
vendor/bin/sail artisan test
```

### Executar Testes E2E de Navegador (Laravel Dusk)
```bash
vendor/bin/sail artisan dusk
```

### Verificar Cobertura de Código
```bash
vendor/bin/sail php scripts/check-coverage.php
```

---

## 🚫 Escopo Explícito (Fora de Alcance)

- **Pagamentos / Billing / Assinatura**: O sistema **não inclui módulo financeiro ou processamento de pagamentos**. O campo `cnpj` da tabela `organizations` é utilizado exclusivamente para identificação cadastral e institucional na emissão de certificados.

---

## 📄 Licença

Este repositório contém software proprietário desenvolvido para a Plataforma EAD. Todos os direitos reservados.
