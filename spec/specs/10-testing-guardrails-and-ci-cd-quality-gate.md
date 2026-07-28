# **Especificação Técnica 10: Suíte de Testes Automatizados, Guardrails de Qualidade e Esteira CI/CD (95%+ Coverage)**

---

## **1. Visão Geral e Escopo Técnico**

### **1.1. Resumo do Módulo**
Este módulo estabelece a infraestrutura técnica de garantia de qualidade, estabilidade e manutenibilidade da aplicação em ambientes de **hospedagem compartilhada**. O projeto adota uma política de **tolerância zero para degradações de código**, implementando um **Guardrail Rígido de Cobertura de Código de no mínimo 95%** em todas as camadas da aplicação (Backend PHPUnit/Pest e Frontend E2E Dusk/Playwright).

Nenhuma alteração de código ou funcionalidade pode ser integrada ou implantada em produção se:
1. A cobertura total de linhas de código ficar abaixo do limiar obrigatório de **95,00%**.
2. Qualquer teste unitário, de integração ou E2E falhar.
3. Houver regressão nos cenários críticos de segurança e isolamento por matrícula (**RN08 / RF20**).

---

### **1.2. Mapeamento de Requisitos e Casos de Uso**

* **Requisitos Funcionais (RF):**
  * **RF19 (Suíte de Testes e Guardrails):** Cobertura mínima obrigatória de **95%** de testes automatizados (Unitários, Integração e E2E) para todos os módulos.
* **Requisitos Não-Funcionais (RNF):**
  * **RNF03 (Testabilidade e Guardrails):** Cobertura de testes de no mínimo **95%** auditada por ferramentas de coverage (Pest/PHPUnit/Dusk/Cypress) cobrindo happy paths e edge cases.
* **Regras de Negócio (RN):**
  * **RN06 (Guardrail de Cobertura de Testes - 95%):** Nenhuma alteração pode ser implantada se a cobertura de testes for inferior a 95% ou se qualquer teste falhar.
* **Casos de Uso (UC):**
  * **UC16 (Suíte de Testes Automatizados e Guardrails de Qualidade - 95%+ Coverage):** Execução automatizada de testes Unitários, Integração e E2E garantindo no mínimo 95% de cobertura antes do deploy.

---

## **2. Configuração do Ambiente de Testes (`phpunit.xml`)**

### **2.1. Arquivo de Configuração Completo: `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnRisky="true"
         failOnWarning="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>app</directory>
        </include>
        <exclude>
            <directory>app/Console</directory>
            <directory>app/Providers</directory>
            <file>app/Http/Middleware/Authenticate.php</file>
        </exclude>
    </source>

    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="PULSE_ENABLED" value="false"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="TELESCOPE_ENABLED" value="false"/>
    </php>

    <logging>
        <junit outputFile="storage/coverage/junit.xml"/>
    </logging>
</phpunit>
```

---

## **3. Script de Auditoria do Guardrail de Cobertura (`scripts/check-coverage.php`)**

Este script em PHP CLI lê o relatório Clover XML de cobertura gerado pelo PHPUnit/PCOV/Xdebug e calcula a porcentagem exata de linhas cobertas. Se a cobertura for inferior a 95.00%, o script encerra a execução com código de erro `1`, abortando o pipeline de CI/CD.

### **3.1. Código do Auditor: `scripts/check-coverage.php`**

```php
<?php

/**
 * Audit Script for 95%+ Code Coverage Guardrail (RN06 / RNF03)
 */

$cloverPath = __DIR__ . '/../storage/coverage/clover.xml';
$minCoverage = 95.00;

if (!file_exists($cloverPath)) {
    fwrite(STDERR, "❌ ERRO CRÍTICO: Arquivo de relatório de cobertura não encontrado em {$cloverPath}\n");
    fwrite(STDERR, "Certifique-se de executar 'vendor/bin/sail test --coverage-clover storage/coverage/clover.xml'\n");
    exit(1);
}

$xml = simplexml_load_file($cloverPath);
if (!$xml || !isset($xml->project->metrics)) {
    fwrite(STDERR, "❌ ERRO CRÍTICO: Falha ao ler métricas do arquivo clover.xml\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$totalStatements = (float) $metrics['elements'] - (float) $metrics['methods'];
$coveredStatements = (float) $metrics['coveredelements'] - (float) $metrics['coveredmethods'];

// Cálculo do percentual de linhas cobertas
$statementsCoverage = $totalStatements > 0 ? ($coveredStatements / $totalStatements) * 100 : 100.0;

$linesTotal = (float) $metrics['statements'];
$linesCovered = (float) $metrics['coveredstatements'];
$lineCoveragePercentage = $linesTotal > 0 ? ($linesCovered / $linesTotal) * 100 : 100.0;

echo "=======================================================\n";
echo "📊 AUDITORIA DE COBERTURA DE CÓDIGO (QUALITY GATE)\n";
echo "=======================================================\n";
echo sprintf("Linhas Totais Executáveis: %d\n", $linesTotal);
echo sprintf("Linhas Cobertas por Testes: %d\n", $linesCovered);
echo sprintf("Percentual de Cobertura Atual: %.2f%%\n", $lineCoveragePercentage);
echo sprintf("Meta Mínima Obrigatória: %.2f%%\n", $minCoverage);
echo "=======================================================\n";

if ($lineCoveragePercentage < $minCoverage) {
    echo sprintf(
        "❌ FALHA NO QUALITY GATE: A cobertura de código (%.2f%%) está abaixo do mínimo exigido (%.2f%%).\n",
        $lineCoveragePercentage,
        $minCoverage
    );
    echo "Implantação bloqueada pela Regra de Negócio RN06.\n";
    exit(1);
}

echo "✅ QUALITY GATE APROVADO: Cobertura de código dentro dos parâmetros exigidos (>= 95.00%).\n";
exit(0);
```

---

## **4. Esteiras de Integração Contínua (CI/CD Pipelines)**

### **4.1. GitHub Actions: `.github/workflows/ci.yml`**

```yaml
name: CI Quality Gate & Testing Guardrail

on:
  push:
    branches: [ "main", "develop" ]
  pull_request:
    branches: [ "main", "develop" ]

jobs:
  test-and-coverage:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: secret_password
          MYSQL_DATABASE: ead_testing
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout Código Source
        uses: actions/checkout@v3

      - name: Configurar Ambiente PHP & PCOV
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, dom, fileinfo, mysql, pdo_mysql, pcov
          coverage: pcov

      - name: Instalar Dependências do Composer
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Preparar Diretórios e Configurações
        run: |
          mkdir -p storage/coverage
          cp .env.example .env.testing
          php artisan key:generate --env=testing

      - name: Executar Formatação e Linter (Laravel Pint)
        run: vendor/bin/pint --test

      - name: Executar Suíte de Testes com Relatório Clover
        run: vendor/bin/phpunit --coverage-clover storage/coverage/clover.xml

      - name: Auditar Cobertura de Código (Guardrail 95%+)
        run: php scripts/check-coverage.php
```

---

## **5. Matriz de Casos de Borda e Erros Obrigatoriamente Testados (Edge Cases)**

A suíte de testes deve cobrir exaustivamente a matriz abaixo para garantir o selo de 95%+ de cobertura sem brechas de segurança:

| ID Caso | Módulo | Cenário de Borda / Erro | Resultado Esperado |
| :--- | :--- | :--- | :--- |
| **EC01** | Autenticação | Tentativa de login com senha inválida ou CPF inexistente | Retornar erro 422 com mensagem de credenciais inválidas. |
| **EC02** | Isolamento (RF20) | Aluno tenta acessar URL de curso `/aluno/cursos/99` sem matrícula | Middleware `EnsureStudentIsEnrolled` retorna HTTP 403 Forbidden. |
| **EC03** | Convite Inteligente | Link de convite com expiração no passado ou limite `max_uses` atingido | Retornar mensagem de convite expirado e bloquear cadastro. |
| **EC04** | Convite Inteligente | E-mail do convite já cadastrado na tabela `users` | Exibir mensagem adaptativa solicitando confirmação de senha do usuário existente. |
| **EC05** | Questionários | Tentativa de submissão simultânea de respostas em duas abas do navegador | Processar apenas 1 tentativa e bloquear a segunda se `allow_retries = false`. |
| **EC06** | Certificados | Solicitação de certificado para curso com progresso de aulas em 99% (faltando 1%) | Exceção `CertificateNotAllowedException` com HTTP 400 Bad Request. |
| **EC07** | Certificados | Validação de certificado público com hash adulterado em 1 caractere | Exibir status "Certificado Inválido ou Inexistente". |
| **EC08** | Fórum | Tópico do fórum com script `<script>alert('XSS')</script>` no conteúdo | Sanitização completa HTML via Blade/Purifier eliminando XSS. |
| **EC09** | Exportação CSV | Exportação de 100.000 alunos em hospedagem compartilhada | Processar via *streaming* `chunk()` mantendo o consumo de RAM em $< 16MB$. |

---

## **6. Lista de Tarefas de Desenvolvimento (Checklist)**

- [ ] **Configurar `phpunit.xml`:** Definir diretórios de inclusão e exclusão de coverage.
- [ ] **Criar Script Auditor:** Escrever `scripts/check-coverage.php` para validar limiar de 95,00%.
- [ ] **Implementar Pipeline CI:** Configurar `.github/workflows/ci.yml` rodando testes e auditor de cobertura.
- [ ] **Escrever Matriz de Edge Cases:** Desenvolver testes automatizados para todos os itens da tabela de casos de borda (EC01 a EC09).
- [ ] **Validação Geral:** Garantir que o comando `vendor/bin/phpunit` atinja 95%+ de cobertura com 0 erros.
