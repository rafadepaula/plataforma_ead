---
name: laravel-verification
description: "Verification loop for Laravel projects: env checks, linting, static analysis, tests with coverage, security scans, deployment readiness."
metadata:
  origin: ECC
---

# Laravel Verification Loop

Run before PRs, after big changes, pre-deploy.

## When to Use

- Before open pull request for Laravel project
- After big refactor or dependency upgrade
- Pre-deployment check for staging or production
- Run full lint -> test -> security -> deploy readiness pipeline

## How It Works

- Run phases in order, environment checks through deployment readiness. Each layer builds on last.
- Environment and Composer checks gate everything else. Stop immediately if they fail.
- Linting/static analysis clean before full tests and coverage.
- Security and migration review after tests, so behavior verified before data or release steps.
- Build/deploy readiness and queue/scheduler checks are final gates. Any failure blocks release.

## Phase 1: Environment Checks

```bash
php -v
composer --version
php artisan --version
```

- Verify `.env` present, required keys exist
- Confirm `APP_DEBUG=false` for production
- Confirm `APP_ENV` matches target deployment (`production`, `staging`)

Laravel Sail locally:

```bash
./vendor/bin/sail php -v
./vendor/bin/sail artisan --version
```

## Phase 1.5: Composer and Autoload

```bash
composer validate
composer dump-autoload -o
```

## Phase 2: Linting and Static Analysis

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

Project uses Psalm instead of PHPStan:

```bash
vendor/bin/psalm
```

## Phase 3: Tests and Coverage

```bash
php artisan test
```

Coverage (CI):

```bash
XDEBUG_MODE=coverage php artisan test --coverage
```

CI example (format -> static analysis -> tests):

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
XDEBUG_MODE=coverage php artisan test --coverage
```

## Phase 3.5: Laravel Dusk Browser Testing (E2E)

UI, frontend components, or browser interactions modified: run E2E browser tests.

```bash
php artisan dusk
```

Laravel Sail locally:

```bash
./vendor/bin/sail artisan dusk
```

Filter specific Dusk tests:

```bash
./vendor/bin/sail artisan dusk --filter=testName
./vendor/bin/sail artisan dusk tests/Browser/LoginTest.php
```

- Dusk test fails, check failure artifacts in `tests/Browser/screenshots/` and `tests/Browser/console/`.
- Keep Chrome/Chromium drivers updated (`php artisan dusk:chrome-driver`).


## Phase 4: Security and Dependency Checks

```bash
composer audit
```

## Phase 5: Database and Migrations

```bash
php artisan migrate --pretend
php artisan migrate:status
```

- Review destructive migrations carefully
- Migration filenames follow `Y_m_d_His_*` (e.g., `2025_03_14_154210_create_orders_table.php`) and describe change clearly
- Rollbacks possible
- Verify `down()` methods, avoid irreversible data loss without explicit backups

## Phase 6: Build and Deployment Readiness

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Cache warmups succeed in production config
- Verify queue workers and scheduler configured
- Confirm `storage/` and `bootstrap/cache/` writable in target environment

## Phase 7: Queue and Scheduler Checks

```bash
php artisan schedule:list
php artisan queue:failed
```

Horizon used:

```bash
php artisan horizon:status
```

`queue:monitor` available: use it to check backlog without processing jobs.

```bash
php artisan queue:monitor default --max=100
```

Active verification (staging only): dispatch no-op job to dedicated queue, run single worker to process it (needs non-`sync` queue connection configured).

```bash
php artisan tinker --execute="dispatch((new App\\Jobs\\QueueHealthcheck())->onQueue('healthcheck'))"
php artisan queue:work --once --queue=healthcheck
```

Verify job produced expected side effect (log entry, healthcheck table row, or metric).

Run only on non-production environments where processing test job is safe.

## Examples

Minimal flow:

```bash
php -v
composer --version
php artisan --version
composer validate
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
composer audit
php artisan migrate --pretend
php artisan config:cache
php artisan queue:failed
```

CI-style pipeline:

```bash
composer validate
composer dump-autoload -o
vendor/bin/pint --test
vendor/bin/phpstan analyse
XDEBUG_MODE=coverage php artisan test --coverage
composer audit
php artisan migrate --pretend
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan schedule:list
```
