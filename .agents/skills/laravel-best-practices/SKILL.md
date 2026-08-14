---
name: laravel-best-practices
description: "Use when write, review, or refactor Laravel PHP code. Covers controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, Eloquent queries. Triggers: N+1, query performance, caching, authorization, security, validation, error handling, queues, jobs, routes, architecture decisions. Also Laravel code review and refactor to best practices. Any Laravel backend PHP code pattern task."
license: MIT
metadata:
  author: laravel
---

# Laravel Best Practices

Best practices for Laravel, index of rule files. Each rule file teaches what to do, why. For exact API syntax, verify with `search-docs`.

## Consistency First

Before apply rule, check what app already does. Laravel offers many valid ways. Best choice is way codebase already uses, even if other pattern theoretically better. Inconsistency worse than suboptimal pattern.

Check sibling files, related controllers, models, tests for established pattern. Pattern exists, follow it. No second way. These rules are defaults for when no pattern exists yet, not overrides.

## How to Apply

1. Check changed files, nearby code, project config, relevant tests for established pattern. Deviate only for correctness or security defect, and call deviation out.
2. Map every affected concern to rule index below. Read each mapped rule file before edit. Skip unrelated rule files.
3. Make smallest coherent change. Keep app architecture and naming. No second pattern for same job.
4. Verify version-sensitive Laravel APIs for installed version with `search-docs`, or inspect installed framework when unavailable.
5. Run narrowest relevant tests first, then project formatting and static-analysis checks when change warrants.
6. Re-read diff against every mapped rule before finish.

## Rule Index

Cross-cutting change often needs more than one rule file.

| Concern | Read |
| --- | --- |
| Query count, eager loading, indexes, large datasets | [`rules/db-performance.md`](rules/db-performance.md) |
| Subqueries, aggregates, complex ordering and query plans | [`rules/advanced-queries.md`](rules/advanced-queries.md) |
| Models, relationships, scopes, casts | [`rules/eloquent.md`](rules/eloquent.md) |
| Authentication, authorization, input safety, secrets, uploads | [`rules/security.md`](rules/security.md) |
| Form Requests and validation rules | [`rules/validation.md`](rules/validation.md) |
| Controllers, route binding, resources, middleware | [`rules/routing.md`](rules/routing.md) |
| Schema changes, columns, foreign keys, indexes | [`rules/migrations.md`](rules/migrations.md) |
| Jobs, retries, uniqueness, batches, Horizon | [`rules/queue-jobs.md`](rules/queue-jobs.md) |
| Cache lifetime, invalidation, locks, memoization | [`rules/caching.md`](rules/caching.md) |
| Outbound requests, retries, timeouts, fakes | [`rules/http-client.md`](rules/http-client.md) |
| Exceptions, reporting, rendering, log context | [`rules/error-handling.md`](rules/error-handling.md) |
| Events and notifications | [`rules/events-notifications.md`](rules/events-notifications.md) |
| Mailables and mail assertions | [`rules/mail.md`](rules/mail.md) |
| Scheduled tasks and overlap protection | [`rules/scheduling.md`](rules/scheduling.md) |
| Collections, lazy iteration, bulk operations | [`rules/collections.md`](rules/collections.md) |
| Blade components, attributes, composers | [`rules/blade-views.md`](rules/blade-views.md) |
| Environment values and application configuration | [`rules/config.md`](rules/config.md) |
| Pest/PHPUnit patterns, factories, fakes | [`rules/testing.md`](rules/testing.md) |
| Naming, helpers, file boundaries, PHP style | [`rules/style.md`](rules/style.md) |
| Actions, services, dependencies, application structure | [`rules/architecture.md`](rules/architecture.md) |

## Decision Rules

- Prefer framework features and existing app abstractions over new helpers or dependencies.
- No speculative abstraction. Extract code when it creates clear domain boundary, removes real duplication, or makes behavior independently testable.
- Keep DB access out of Blade views. Prevent hidden N+1 across controllers, resources, jobs, serialization.
