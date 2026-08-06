# **Especificação de Caso de Uso: UC23 — Menu de Navegação Dinâmico e Controle de Acesso por Perfil**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC23
* **Nome:** Menu de Navegação Dinâmico e Controle de Acesso por Perfil
* **Módulo:** Interface e Controle de Navegação (`Dynamic Navigation`)
* **Atores Principais:** Aluno Capacitando, Gestor de Organização, Administrador Global
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF26** | Renderizar a estrutura de navegação lateral/topbar via `NavigationRegistry` e `NavigationComposer`, adaptando os menus por Role (`admin`, `gestor`, `aluno`). |
| **Regra de Negócio** | **RN08** | Restrição de Acesso por Perfil. |
| **Regra de Negócio** | **RN12** | Adaptabilidade do Menu sob Impersonate Org. |

---

## **3. Visão Geral e Objetivo**

Renderizar dinamicamente a estrutura de menus da barra lateral (*sidebar*) e do cabeçalho superior (*topbar*) em todas as views da aplicação através da arquitetura desacoplada `NavigationRegistry`, `NavigationService` e `NavigationComposer`. Os itens de menu exibidos, links atalhos e contadores são estritamente filtrados com base nas permissões e na Role do usuário autenticado (`admin`, `gestor`, `aluno`), destacando automaticamente o item ativo correspondente à rota atual.

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Usuário autenticado navegando em qualquer rota privada da aplicação.

### **4.2. Pós-condições**
* Menu lateral renderizado contendo exatamente os módulos autorizados para o perfil do usuário logado.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Renderização Dinâmica do Menu**

1. O usuário faz uma requisição para qualquer rota autenticada da aplicação (ex: `/meus-cursos` ou `/admin/dashboard`).
2. O Laravel aciona o View Composer `NavigationComposer` registrado para o layout master `layouts.app`.
3. O `NavigationComposer` consulta o `NavigationService::getMenuForUser($user)`:
   - O `NavigationRegistry` obtém a árvore completa de itens cadastrados.
   - Aplica a filtragem por Role:
     - **Para `role:aluno`:** Exibe apenas "Meus Cursos" (`/meus-cursos`), "Minhas Notificações" e "Central de Ajuda". Oculta módulos administrativos.
     - **Para `role:gestor`:** Exibe "Dashboard", "Cursos & Conteúdos", "Alunos & Matrículas", "Fórum de Moderação", "Auditoria da Org", "Configurações".
     - **Para `role:admin`:** Exibe a visão gestor estendida com os módulos globais: "Organizações" (`/organizations`) e "Auditoria Global".
   - Executa a checagem de rota ativa (`Route::is('nome_da_rota*')`) e injeta a classe CSS `active` no item correspondente.
4. O Blade renderiza a partial `components.layout.sidebar` com a árvore tratada.
5. A interface exibe os itens de menu corretos com seus ícones, títulos e destaque na página atual.

---

## **6. Assinatura Técnica de Componentes**

* **Classes:** `App\Navigation\NavigationRegistry`, `App\Services\NavigationService`, `App\View\Composers\NavigationComposer`.
* **Blade Partials:** `resources/views/components/layout/sidebar.blade.php`, `resources/views/components/layout/topbar.blade.php`.
