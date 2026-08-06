# **Especificação de Caso de Uso: UC21 — Suíte de Testes Automatizados, Environment Dusk e CI/CD Quality Gate**

---

## **1. Identificação do Caso de Uso**

* **ID:** UC21
* **Nome:** Suíte de Testes Automatizados, Environment Dusk e CI/CD Quality Gate
* **Módulo:** Testes e Quality Gate (`Testing & CI/CD`)
* **Atores Principais:** Desenvolvedor, Esteira CI/CD (GitHub Actions / GitLab CI)
* **Versão:** 2.0 (Multitenant)

---

## **2. Requisitos Funcionais e Regras de Negócio Vinculados**

| Tipo | Código | Descrição |
| :--- | :--- | :--- |
| **Requisito Funcional** | **RF19** | Cobertura mínima obrigatória de **95%** de testes automatizados (PHPUnit + Dusk E2E em banco MySQL `testing` dedicado). |
| **Regra de Negócio** | **RN06** | **Guardrail de Cobertura de Testes (95%):** Nenhuma alteração pode ser implantada se a cobertura for inferior a 95% ou se qualquer teste falhar. |

---

## **3. Visão Geral e Objetivo**

Garantir a estabilidade, regressão zero e manutenibilidade da aplicação multitenant através da execução automatizada da suíte de testes unitários, de integração (PHPUnit) e de navegador ponta a ponta (Laravel Dusk E2E em banco MySQL dedicado), bloqueando deploys na esteira CI/CD caso a cobertura global de código fique abaixo da meta estrita de 95% (RN06).

---

## **4. Condições do Sistema**

### **4.1. Pré-condições**
* Ambiente configurado com banco de dados MySQL de testes `testing` (`.env.dusk.local`).
* ChromeDriver instalado e ativo para testes E2E.

### **4.2. Pós-condições**
* Relatório de testes executado com 100% de aprovação.
* Relatório de cobertura XML (`coverage.xml`) gerado e auditado pelo `scripts/check-coverage.php`.

---

## **5. Fluxos de Execução Detalhados**

### **5.1. Fluxo Principal: Execução Automatizada e Auditoria no CI/CD**

1. O desenvolvedor envia um Pull Request ou aciona o pipeline de integração contínua (`git push`).
2. O runner do CI/CD dispara o script de testes backend:
   `vendor/bin/sail artisan test --compact --coverage-clover coverage.xml`
3. O PHPUnit executa todas as suítes de testes Feature e Unit com isolamento multitenant.
4. O runner dispara a suíte de testes E2E com Laravel Dusk:
   `vendor/bin/sail artisan dusk`
5. Os testes Dusk iniciam o navegador Headless Chrome, acessam a aplicação no ambiente `testing`, realizam interações do usuário (logins, drag-and-drop de módulos, submissão de provas, clique em convites) e confirmam a renderização visual das páginas.
6. Ao finalizar os testes com 100% de aprovação, o runner aciona o script auditor de cobertura:
   `php scripts/check-coverage.php coverage.xml 95`
7. O script analisa o XML de cobertura:
   - Se a porcentagem calculada for **>= 95.00%**: Retorna código de saída `0` (Sucesso) e libera o deploy.
   - Se for **< 95.00%**: Retorna código de saída `1` (Erro) e cancela o pipeline.

---

## **6. Assinatura Técnica de Comandos**

* **Comandos CLI:**
  - `vendor/bin/sail artisan test --compact`
  - `vendor/bin/sail artisan dusk`
  - `php scripts/check-coverage.php coverage.xml 95`
* **Ambiente Dusk:** `.env.dusk.local` (`DB_DATABASE=plataforma_ead_testing`).
