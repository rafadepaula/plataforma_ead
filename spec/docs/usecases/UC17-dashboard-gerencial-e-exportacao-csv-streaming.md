# **Especificação de Caso de Uso: UC17 — Dashboard Gerencial, Analytics e Exportação CSV em Streaming**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC17
* **Nome:** Dashboard Gerencial, Analytics e Exportação CSV em Streaming
* **Módulo:** Analytics e Relatórios (`Dashboard & Reports Streaming`)
* **Atores Principais:** Administrador Global, Gestor de Organização
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF18** | Exibir métricas gerenciais e permitir a exportação de dados detalhados em CSV via streaming. |
| **Regra de Negócio** | **RN08** | Restrição de Escopo Tenant por Organização. |
| **Regra de Negócio** | **RN12** | Visão Admin (Global ou Impersonate Org) vs Visão Gestor (Org Local). |
| **Regra de Negócio** | **RN14** | Auditoria de Exportação em Lote (`csv.export`). |
| **Regra Não-Funcional** | **RNF06** | **Streaming $O(1)$ de RAM:** Relatórios em CSV devem utilizar `StreamedResponse` com consumo de memória constante. |

---

## **3. Visão Geral e Objetivo**

Apresentar o painel de métricas pedagógicas e gerenciais para administradores e gestores (contendo 4 stat cards principais e listagem das matrículas mais recentes) e disponibilizar a Central de Exportação de Relatórios em arquivos CSV gerados via streaming $O(1)$ de memória para evitar sobrecarga de memória do servidor.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Operador autenticado com perfil `admin` ou `gestor`.

### **4.2. Pós-condições**
* Dashboard renderizado com dados agregados filtrados por `OrgScope`.
* Arquivo CSV transmitido via download direto sem buffering em RAM.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Visualização do Dashboard Gerencial**

1. O operador clica no menu **"Dashboard"** (`GET /admin/dashboard`).
2. O `DashboardController::index()` utiliza o `DashboardMetricsService` para agregar os dados:
   - Se Gestor: Filtra estritamente por `org_id` do usuário.
   - Se Admin sem Impersonate: Agrega métricas globais de todas as Organizações.
   - Se Admin com Impersonate: Agrega dados da Organização selecionada em `session('active_org_id')`.
3. A view `admin.dashboard` renderiza:
   - **Stat Card 1 (Cursos Cadastrados):** Total de cursos e variação.
   - **Stat Card 2 (Alunos Capacitando):** Total de alunos vinculados.
   - **Stat Card 3 (Matrículas Ativas):** Total em `course_user` com status `active`.
   - **Stat Card 4 (Certificados Emitidos):** Total em `certificates`.
   - **Tabela de Matrículas Recentes:** Lista as últimas 10 matrículas realizadas com Aluno, Curso, Organização e Data.

---

### **5.2. Fluxo Principal 2: Exportação de Relatório CSV em Streaming (RNF06)**

1. No Dashboard ou na Central de Relatórios, o gestor clica no botão **"Exportar Matrículas (CSV)"** ou **"Exportar Certificados (CSV)"**.
2. A requisição `GET /admin/reports/{type}/export` é disparada (onde `{type}` é `enrollments` ou `certificates`).
3. O `ReportExportController::stream()` valida os parâmetros:
   - Impede que um Gestor passe parâmetros para ler dados de outra Organização.
4. O controller constrói a consulta Eloquent usando `cursor()` ou `chunk(500)` e retorna uma `Symfony\Component\HttpFoundation\StreamedResponse`:
   - Define headers: `Content-Type: text/csv`, `Content-Disposition: attachment; filename="relatorio_{type}_{date}.csv"`.
   - Abre o output stream `php://output`.
   - Escreve a linha de cabeçalho em UTF-8 (com BOM `\xEF\xBB\xBF` para compatibilidade com Excel).
   - Itera sobre a query escrevendo cada linha diretamente no buffer de saída HTTP (`fputcsv`).
5. O navegador inicia o download do arquivo imediatamente enquanto o servidor processa o streaming com uso constante de memória ($O(1)$ RAM).
6. O `AuditService` grava o evento de auditoria `csv.export`.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Tipo de Relatório Desconhecido**
* **Gatilho:** Acesso a `/admin/reports/invalid_type/export`.
* **Comportamento:** A rota com validação `whereIn('type', ['enrollments', 'certificates'])` rejeita o acesso e retorna erro HTTP 404 (Not Found).

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /admin/dashboard` (`name: admin.dashboard`), `GET /admin/reports/{type}/export` (`name: reports.export`).
* **Middleware:** `auth`, `role:admin|gestor`.
* **Controllers & Services:** `DashboardController`, `ReportExportController`, `App\Services\DashboardMetricsService`.
