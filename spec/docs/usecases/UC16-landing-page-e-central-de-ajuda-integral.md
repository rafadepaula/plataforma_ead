# **Especificação de Caso de Uso: UC16 — Landing Page Pública e Central de Ajuda Integral com Fallback**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC16
* **Nome:** Landing Page Pública e Central de Ajuda Integral com Fallback
* **Módulo:** Suporte e Central de Ajuda (`Landing Page & Help Center`)
* **Atores Principais:** Visitante, Aluno, Gestor de Organização, Administrador Global
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF11** | Página inicial pública (`/`) apresentando a plataforma, seus objetivos institucionais e atalhos para login/ajuda. |
| **Requisito Funcional** | **RF12** | Disponibilizar área de ajuda cobrindo 100% das páginas/funcionalidades, com links contextuais presentes em todas as telas. |
| **Regra de Negócio** | **RN05** | **Cobertura de Ajuda 100%:** Todas as telas da aplicação possuem o componente `<x-help-button key="..." />` que consulta artigos com fallback org-específica -> global. |
| **Regra de Negócio** | **RN08** | Acesso às páginas públicas sem restrição de autenticação. |
| **Regra de Negócio** | **RN12** | Resolução dinâmica do `HelpArticleResolverService` por `org_id` e fallback global (`org_id IS NULL`). |

---

## **3. Visão Geral e Objetivo**

Apresentar a plataforma institucionalmente ao público geral através de uma Landing Page indexável (`/`) e fornecer suporte técnico/operacional contínuo através da Central de Ajuda. A Central de Ajuda garante cobertura de 100% das telas da aplicação através do componente Blade reutilizável `<x-help-button key="..." />`, abrindo um modal contextual dinâmico que busca artigos específicos da Organização ativa ou recorre ao artigo padrão global como fallback (RN05).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Para a Landing Page: Acesso público.
* Para o botão de ajuda: O componente `<x-help-button key="chave_da_tela" />` inserido na view Blade da tela ativa.

### **4.2. Pós-condições**
* Artigo de ajuda localizado e renderizado no modal de suporte sem recarregar a tela.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal 1: Navegação na Landing Page Pública**

1. O visitante acessa a URL raiz da aplicação `/`.
2. O `LandingPageController::show()` renderiza a página pública `landing.show` (sem exigir login).
3. A página exibe:
   - Header com logo institucional do conselho, links "Sobre", "Ajuda" e botão destacado **"Entrar na Plataforma"**.
   - Hero Banner com chamada principal para a capacitação de eletricistas.
   - Grade de Benefícios e recursos da plataforma EAD.
   - Footer com links úteis.

---

### **5.2. Fluxo Principal 2: Acionamento da Ajuda Contextual em Qualquer Tela (RN05)**

1. O usuário (Aluno, Gestor ou Admin) navega em qualquer tela do sistema (ex: `/meus-cursos`, `/courses/create`, `/admin/dashboard`, `/convite/{token}`).
2. No cabeçalho ou canto da página, o usuário visualiza o botão de ajuda contextual `<x-help-button key="dashboard.index" />` (ícone de interrogação `?` com o rótulo "Ajuda").
3. O usuário clica no botão de ajuda.
4. O componente aciona o script `HelpCenterModal.js`, que faz uma chamada AJAX `GET /ajuda/artigo?key=dashboard.index`.
5. O backend executa a busca através do `HelpArticleResolverService::resolve($key)`:
   - **Passo A (Busca Tenant):** Se houver usuário logado com `org_id` ou `session('active_org_id')`, busca em `help_articles` por `target_page_key = $key` AND `org_id = $orgId`.
   - **Passo B (Fallback Global):** Se não encontrar registro específico da Org, busca em `help_articles` por `target_page_key = $key` AND `org_id IS NULL`.
6. O serviço retorna a estrutura do artigo (Título, Conteúdo Rich Text e Categoria).
7. O modal `.dialog-backdrop` do `HelpCenterModal.js` exibe o artigo de ajuda na tela sobreposta sem interromper a navegação do usuário.

---

## **6. Fluxos de Exceção**

### **6.1. Fluxo de Exceção 1: Chave de Tela sem Artigo Cadastrado**
* **Gatilho:** Clique em `<x-help-button key="tela.sem.artigo" />` quando não existe artigo específico nem global.
* **Comportamento:** O `HelpArticleResolverService` retorna a resposta padrão de fallback, e o modal exibe a mensagem amigável: *"Estamos preparando o artigo de ajuda para esta tela. Em breve as orientações estarão disponíveis."*

---

## **7. Assinatura Técnica de Rotas e Componentes**

* **Rotas:** `GET /` (`LandingPageController@show`), `GET /ajuda/artigo` (`HelpArticleController@resolve`).
* **Componente Blade:** `<x-help-button key="..." />`.
* **Service:** `App\Services\HelpArticleResolverService`.
* **JS Asset:** `public/js/modules/HelpCenterModal.js`.
