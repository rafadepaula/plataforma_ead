---
name: testing-maintenance
description: Guia de manutenção, debug, flakiness, edge cases da infraestrutura de testes e CI/CD.
---

# Testing Maintenance (`testing-maintenance`)

## Overview

Documento dá instruções para diagnóstico de erro, manutenção contínua e solução de problemas comuns nas suítes de teste da Plataforma EAD.

---

## Diagnóstico e Debugging

1. **Falhas em Testes Laravel Dusk**:
   - Ver capturas de tela em `tests/Browser/screenshots/` e logs em `tests/Browser/console/`.
   - Garantir driver do Chrome sincronizado:
     ```bash
     php artisan dusk:chrome-driver --detect
     ```
   - Garantir aplicação servida antes dos testes Dusk (ex: `php artisan serve --port=8000 &`).

2. **Auditoria e Verificação de Cobertura**:
   - Gerar relatório de cobertura local com Xdebug:
     ```bash
     XDEBUG_MODE=coverage php artisan test --testsuite=Unit,Feature --coverage-clover=storage/coverage/clover.xml
     php scripts/check-coverage.php
     ```
   - Script `scripts/check-coverage.php` verifica só o arquivo `storage/coverage/clover.xml` pré-existente e calcula se porcentagem de cobertura atinge mínimo exigido de 95,00%.

3. **Execução de Testes via Sail**:
   - Rodar suíte PHPUnit:
     ```bash
     vendor/bin/sail artisan test
     ```
   - Rodar formatador Pint em arquivos modificados:
     ```bash
     vendor/bin/sail bin pint --dirty --format agent
     ```

---

## Resolução de Problemas Comuns (Edge Cases)

- **Instabilidade (Flakiness) em Testes Dusk**:
  - Evitar `sleep()`. Usar método explícito tipo `$browser->waitForText(...)` ou `$browser->waitUntilMissing(...)`.

### Depurando um Teste Encadeado por Ciclo de Vida (Lifecycle Chaining)

Testes E2E deste repositório agrupam jornada inteira em um método (ver `testing-conventions`). Isso muda diagnóstico de falha:

- **Localize a etapa, não só o método**: cada cadeia tem comentário numerado (`// 1. Criação`, `// 2. Edição`...). Stack trace aponta a linha; case a linha com a etapa antes de investigar. Screenshot em `tests/Browser/screenshots/` mostra estado da etapa que falhou, não do início do teste.
- **Falha tardia na cadeia costuma ser efeito de etapa anterior**: `assertSee` que quebra na etapa 4 quase sempre significa etapa 2 não persistiu o que devia. Confira asserções intermediárias de banco (`assertDatabaseHas`). Se passaram, defeito é de UI/rota da etapa atual.
- **Rodar apenas uma cadeia**: `vendor/bin/sail artisan dusk --filter=test_gestor_user_management_full_lifecycle`. Não existe forma de rodar "só a etapa 3": para isolar, comente etapas posteriores temporariamente. **Jamais** quebre a cadeia em métodos permanentes só para depurar (regressão de desempenho).
- **Não introduza dependência entre métodos**: `TRUNCATE` entre testes apaga tudo. Se uma cadeia só passa quando outra roda antes, há vazamento de estado. Corrija o setup da cadeia.

### Etapa de Cadeia que Disputa com Recurso Externo (`<iframe>` de terceiros)

Sintoma: `Waited 5 seconds for location [...]` numa etapa de submit que passa isolada e falha na suíte cheia. Causa vista em `LessonMultimediaTest`: a tela de edição da lição renderiza o `<iframe>` de pré-visualização do YouTube; o `waitForLocation` pós-submit disputa com o carregamento do frame externo.

Correções, nesta ordem:
1. Espere pelo **texto do flash** (`waitForText('... com sucesso.')`) em vez da URL — não depende do `load` completo da página.
2. Se a etapa não precisa daquele recurso, mova-a para um registro equivalente sem dependência de rede (no caso: editar a lição de PDF em vez da de YouTube — mesmo caminho de `update`).
3. Nunca "conserte" quebrando a cadeia em métodos atômicos: isso troca 1 flake por N× custo fixo.

### Edge Cases do `DatabaseTruncation`

- **Trait duplicada**: `DatabaseTruncation` vive em `Tests\DuskTestCase`. Redeclarar `DatabaseMigrations` em classe filha volta ao `migrate:fresh` por método e derruba desempenho da suíte inteira sem erro visível. Sintoma: suíte fica minutos mais lenta após PR novo. Verificação rápida:
  ```bash
  grep -rn "DatabaseMigrations" tests/Browser/
  ```
  Resultado esperado: vazio (salvo justificativa escrita no arquivo).
- **Tabela de referência sendo truncada**: dado que precisa sobreviver entre testes (seed de referência) entra em `$exceptTables` na classe base. Hoje: `['migrations', 'roles', 'permissions', 'role_has_permissions']`. Sintoma clássico: primeiro teste passa e TODOS os seguintes explodem com `There is no role named ``admin`` for guard ``web``` — os papéis do Spatie nascem na MIGRAÇÃO `create_permission_tables` (`Role::findOrCreate()` por caso de `RolesEnum`), que sob `DatabaseTruncation` roda uma única vez por suíte. As atribuições usuário→papel (`model_has_roles`) continuam sendo truncadas normalmente.
- **Estado fora do banco não é truncado**: arquivos em `storage/app/public`, cache e sessão persistem entre métodos com `DatabaseTruncation` (diferente do `migrate:fresh`). Cadeia que faz upload deve limpar/nomear arquivo de forma única.
- **Nunca asserte IDs literais** (`'id' => 1`) dentro de uma cadeia: jornada cria vários registros em sequência e ordem/valor do auto-increment é detalhe de implementação. Asserte por atributo de negócio (`email`, `slug`, `validation_hash`).
- **Erro de Cobertura não Gerada**:
  - Garanta extensão Xdebug ou PCOV habilitada no ambiente PHP (`XDEBUG_MODE=coverage`).
- **Dusk rodou contra o banco errado (`plataforma_ead` foi modificado) — RN13/RF30 (SPEC-14)**:
  - Sintoma: dados de desenvolvimento sumiram/foram truncados após `vendor/bin/sail dusk`, ou suíte Dusk aparentemente "passou" mas alterou registros que você reconhece do ambiente local.
  - Diagnóstico:
    1. Script exige subcomando (`snapshot`/`verify`; `getopt()` não é usado por parar de ler flags no primeiro argumento posicional). Rode `vendor/bin/sail php scripts/verify-dev-db-preserved.php snapshot` **antes** de `vendor/bin/sail dusk` e `vendor/bin/sail php scripts/verify-dev-db-preserved.php verify` **depois**. Primeiro grava snapshot (contagem de linhas/checksum via `CHECKSUM TABLE`) das tabelas de `plataforma_ead` em `storage/framework/testing/dusk-db-snapshot.json` (gitignored); segundo re-tira snapshot e compara, falhando (`exit 1`) em qualquer tabela alterada/adicionada/removida.
    2. Confirme `.env.dusk.local` existe na raiz do repositório e contém `DB_DATABASE=testing` e `DB_CONNECTION=mysql` (não `plataforma_ead`).
    3. Verifique `APP_ENV` do `.env` **ativo antes** de rodar Dusk: `DuskCommand` nativo resolve arquivo de troca como `.env.dusk.{environment()}`. Se `.env` local não tiver `APP_ENV=local`, Dusk procura `.env.dusk.<outro-ambiente>` (que não existe) e, sem `.env.dusk` genérico, **continua usando o `.env` ativo**, rodando silenciosamente contra `plataforma_ead`. Corrija `APP_ENV=local` no `.env` de desenvolvimento ou gere manualmente o `.env.dusk.<seu-ambiente>` correspondente.
    4. Confira se volume `sail-mysql` já existia antes desta spec: script `create-testing-database.sh` só roda em `/docker-entrypoint-initdb.d/` na **primeira** criação do volume. Volume antigo pode não ter base `testing`. Valide com `vendor/bin/sail artisan db:show` ou crie manual via `vendor/bin/sail mysql -e "CREATE DATABASE IF NOT EXISTS testing;"`.
  - No CI, equivalente é conferir se passo "Setup CI Dusk environment" (que copia `.env.dusk.ci` por cima do `.env`) rodou **antes** do passo "Run Dusk Browser Tests" no `.github/workflows/ci.yml`, e não antes do passo Unit/Feature (que segue em sqlite via `.env.ci`).
