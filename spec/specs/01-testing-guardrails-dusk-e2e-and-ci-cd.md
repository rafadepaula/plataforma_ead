# **01. Infraestrutura de Testes TDD, Laravel Dusk E2E, Quality Gate e Esteira CI/CD**

---

## **1. Visão Geral e Filosofia TDD (Test-Driven Development)**

Esta especificação define a **Infraestrutura de Testes e Quality Gate da Plataforma EAD**, configurada na **Fase 1 do projeto** para garantir que todo o desenvolvimento de software seja conduzido sob o rigor do **TDD (Test-Driven Development)**.

### **1.1. As Regras Cardinais do TDD**
1. **Configuração Imediata da Infraestrutura:** O ambiente de testes (PHPUnit/Pest, banco em memória SQLite, drivers do Laravel Dusk, script auditor de cobertura e esteira CI/CD) deve estar 100% operacional antes do desenvolvimento de qualquer rota ou página.
2. **Ciclo Vermelho-Verde-Refatorar:** Cada nova funcionalidade exige a escrita prévia do teste automatizado (unitário, feature e/ou E2E Dusk) demonstrando a falha, seguido da implementação mínima para fazê-lo passar e refatoração com segurança.
3. **Guardrail de Cobertura (Mínimo 95,00%):** A suíte de testes backend deve cobrir no mínimo **95,00%** das linhas de código auditada por `scripts/check-coverage.php`.
4. **Testes E2E Laravel Dusk (100% de Telas e Fluxos):** Cada funcionalidade e interface desenvolvida DEVE conter suíte de testes E2E com **Laravel Dusk** simulando interações reais de navegadores (cliques, preenchimento de formulários, modais, AJAX jQuery, downloads PDF).
5. **Critério Rígido de Aprovação:** Uma especificação SÓ É CONSIDERADA CONCLUÍDA quando 100% dos testes Unitários, de Integração e Dusk E2E passarem sem nenhuma falha.

---

## **2. Configuração do Ambiente de Testes (`phpunit.xml`)**

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true" stopOnFailure="false">
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
        <testsuite name="Feature"><directory>tests/Feature</directory></testsuite>
        <testsuite name="Browser"><directory>tests/Browser</directory></testsuite>
    </testsuites>
    <source>
        <include><directory>app</directory></include>
        <exclude>
            <directory>app/Console</directory>
            <directory>app/Providers</directory>
        </exclude>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

---

## **3. Script Auditor CLI (`scripts/check-coverage.php`)**

Script CLI responsável por analisar o relatório `storage/coverage/clover.xml` e abortar a esteira com erro caso a cobertura seja inferior a 95,00%:

```php
<?php

$cloverFile = __DIR__ . '/../storage/coverage/clover.xml';
if (! file_exists($cloverFile)) {
    echo "Erro: Arquivo clover.xml não encontrado.\n";
    exit(1);
}

$xml = simplexml_load_file($cloverFile);
$metrics = $xml->project->metrics;
$totalStatements = (float) $metrics['elements'];
$coveredStatements = (float) $metrics['coveredelements'];

$coverage = ($totalStatements > 0) ? ($coveredStatements / $totalStatements) * 100 : 0;
echo sprintf("Cobertura de Código Atual: %.2f%%\n", $coverage);

if ($coverage < 95.00) {
    echo "FALHA: Cobertura mínima de 95.00% não atingida.\n";
    exit(1);
}

echo "SUCESSO: Cobertura dentro dos padrões estipulados!\n";
exit(0);
```

---

## **4. Esteira CI/CD (`GitHub Actions`)**

* **Provedor:** GitHub Actions (repositório hospedado no GitHub). Workflow único `.github/workflows/ci.yml`, sem matrix — **PHP 8.5 fixo** (ver SPEC-00 §1.1).
* **Gatilho:** `pull_request` para `main` + `push` em `main`.
* **Passos obrigatórios do job `test`:**
  1. `actions/checkout@v4`
  2. `shivammathur/setup-php@v2` com `php-version: '8.5'`, `extensions: mbstring, pdo_sqlite, dom, curl, zip`, `coverage: xdebug`
  3. `composer install --prefer-dist --no-interaction --no-progress`
  4. `npm ci && npm run build` (assets Vite/Bootstrap necessários para o Dusk renderizar a UI real)
  5. `cp .env.ci .env && php artisan key:generate`
  6. Instalação do Chrome headless: `php artisan dusk:chrome-driver --detect` + start do servidor local (`php artisan serve &` antes do Dusk)
  7. `php artisan test --testsuite=Unit,Feature --coverage-clover=storage/coverage/clover.xml`
  8. `php artisan dusk` (suíte `tests/Browser`)
  9. `php scripts/check-coverage.php` — **falha o job (`exit 1`) se cobertura < 95,00%**
  10. Upload do artefato `storage/coverage/clover.xml` via `actions/upload-artifact@v4` (retenção 14 dias) para auditoria
* **Branch protection:** `main` exige o check `test` verde antes de permitir merge (configuração de settings do repositório, fora do escopo do YAML, mas obrigatória para o guardrail funcionar).
* **Falha de flakiness Dusk:** nenhum retry automático — teste instável deve ser corrigido, não mascarado (proíbe `--retry` silencioso na CI).

---

## **5. Checklist de Implementação & Testes**

- [ ] Configuração do `phpunit.xml` e ambiente de testes SQLite em memória
- [ ] Configuração do **Laravel Dusk** para execução de testes E2E com headless Chrome
- [ ] Implementação do script auditor CLI `scripts/check-coverage.php`
- [ ] Workflow `.github/workflows/ci.yml` conforme §4, com branch protection ativa em `main`
- [ ] Harness: Criar/atualizar as 3 skills (`testing-architecture`, `testing-conventions`, `testing-maintenance`)
