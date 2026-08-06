# **Especificação de Caso de Uso: UC05 — Importação em Lote de Usuários via CSV**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC05
* **Nome:** Importação em Lote de Usuários via CSV
* **Módulo:** Gestão de Usuários (`Users Import`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF05** | Permitir importação de múltiplos alunos via upload de arquivo CSV com processamento assíncrono/chunked e tratamento de erros via `UserImportService`. |
| **Regra de Negócio** | **RN08** | Restrição de Escopo Tenant (todos os usuários importados vinculam-se ao `org_id` ativo). |
| **Regra de Negócio** | **RN12** | Contexto de Impersonate Org no Chunking. |
| **Regra de Negócio** | **RN14** | Registro do Evento de Auditoria `csv.import` em `audit_logs`. |

---

## **3. Visão Geral e Objetivo**

Permitir a criação massiva de contas de usuários (Alunos ou Gestores) a partir de um arquivo CSV formatado, dividindo o arquivo em lotes (*chunks*) processados no cliente via AJAX para evitar estouro de timeout HTTP ou limite de memória em hospedações compartilhadas.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* O operador deve possuir perfil `admin` ou `gestor`.
* O operador deve estar com uma Organização ativa em sessão.
* O arquivo CSV deve estar codificado em UTF-8 com separador vírgula ou ponto-e-vírgula contendo o cabeçalho: `name,email,cpf,role`.

### **4.2. Pós-condições**
* Linhas válidas inseridas em `users` e associadas às respectivas Roles e ao `org_id` ativo.
* Evento `csv.import` gravado em `audit_logs` registrando total processado, sucessos e erros.
* Modal de relatório exibido na tela indicando quais linhas falharam e o motivo.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Importação via Interface e Chunking AJAX**

1. O operador navega até a tela de Usuários (`/users`) e clica no botão **"Importar CSV"** (`GET /users/import`).
2. O `UserImportController::create()` exibe a view `users.import`.
3. A interface apresenta:
   - Área de Upload de Arquivo (Input `type="file"`, `accept=".csv"`).
   - Link para **Download do Modelo CSV de Exemplo**.
   - Barra de Progresso do Importador (inicialmente oculta, `0%`).
   - Botão **"Iniciar Importação"**.
4. O operador seleciona o arquivo CSV no computador e clica em **"Iniciar Importação"**.
5. O módulo JavaScript `CsvImporter.js`:
   - Lê o arquivo localmente no navegador via `FileReader` API.
   - Valida o cabeçalho do arquivo.
   - Divide o conteúdo em lotes de 50 linhas por lote.
6. O JavaScript inicia um loop assíncrono enviando requisições `POST /users/import/chunk` contendo:
   - `rows`: Array de objetos `{name, email, cpf, role}`.
   - `chunk_index`: Índice do lote atual.
7. O backend processa cada linha dentro do `UserImportService`:
   - Se o e-mail já existe na Org ativa, pula ou atualiza.
   - Se o e-mail não existe, cria o `User` em `users` com o `org_id` ativo, gera uma senha temporária aleatória e atribui a Role.
8. Cada resposta AJAX atualiza a barra de progresso em tempo real (ex: `25%`, `50%`, `100%`).
9. Ao concluir todos os lotes, o sistema aciona o `AuditService::log('csv.import')` e exibe o resumo final: Total Processado, Criados com Sucesso e Erros Encontrados (com download do log de erros em CSV).

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Arquivo com Formato ou Cabeçalho Inválido**
* **Gatilho:** Upload de arquivo XLSX ou CSV sem as colunas obrigatórias `name` e `email`.
* **Comportamento:** O JavaScript analisa o cabeçalho antes de enviar ao servidor e exibe imediatamente um alerta vermelho: *"Cabeçalho inválido. O arquivo CSV deve conter exatamente as colunas: name, email, cpf, role."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /users/import` (`UserImportController@create`), `POST /users/import/chunk` (`UserImportController@chunk`).
* **Middleware:** `auth`, `role:admin|gestor`.
* **JS Asset:** `public/js/modules/CsvImporter.js`.
