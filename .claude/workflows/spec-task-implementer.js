export const meta = {
  name: 'spec-task-implementer',
  description: 'Understand a spec task from spec/specs/, tech-refine it against the current codebase, implement it TDD-first via the laravel-tdd RED-GREEN-REFACTOR cycle and the laravel-dusk skill for browser flows, using PHPUnit classes per project convention, verify the full suite, loop code-reviewer, validate-test-quality & spec-usecase-test-checker until clean, then check the module skills for staleness.',
  whenToUse: 'Run once per spec requirement passed via args - spec file, optionally an RF task ref - to take it from spec text to reviewed, tested code with its skills kept in sync.',
  phases: [
    { title: 'Understand', detail: 'Read the spec task, extract requirements/business rules', model: 'opus' },
    { title: 'Tech-Refine', detail: 'Study current codebase, produce a 3-bucket implementation plan', model: 'opus' },
    { title: 'Code', detail: '3 parallel agents, each applying laravel-tdd RED-GREEN-REFACTOR in PHPUnit, using laravel-dusk for any browser-facing bucket', model: 'opus' },
    { title: 'Test', detail: 'Run the full PHPUnit + Dusk suite via Sail, verify coverage per the laravel-tdd checklist', model: 'opus' },
    { title: 'Review', detail: 'code-reviewer, validate-test-quality & spec-usecase-test-checker loop: audit code & test efficacy, verify E2E Dusk coverage for all use cases, fix CONFIRMED findings, capped iterations', model: 'opus' },
    { title: 'Meta-Skill-Check', detail: 'Per SPEC-03, check the touched module skill triad for staleness against the code just merged and update it', model: 'opus' }
  ]
}


const UNDERSTANDING_SCHEMA = {
  type: 'object',
  properties: {
    requirementText: { type: 'string' },
    businessRules: { type: 'array', items: { type: 'string' } },
    dbTables: { type: 'array', items: { type: 'string' } },
    acceptanceCriteria: { type: 'array', items: { type: 'string' } },
    relatedSpecs: { type: 'array', items: { type: 'string' } }
  },
  required: ['requirementText', 'businessRules', 'acceptanceCriteria']
}

const TECH_PLAN_SCHEMA = {
  type: 'object',
  properties: {
    buckets: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          name: { type: 'string' },
          description: { type: 'string' },
          files: { type: 'array', items: { type: 'string' } }
        },
        required: ['name', 'description', 'files']
      }
    },
    edgeCases: { type: 'array', items: { type: 'string' } },
    openQuestions: { type: 'array', items: { type: 'string' } }
  },
  required: ['buckets']
}

const SKILL_CHECK_SCHEMA = {
  type: 'object',
  properties: {
    skillsReviewed: { type: 'array', items: { type: 'string' } },
    skillsCreated: { type: 'array', items: { type: 'string' } },
    skillsUpdated: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          skill: { type: 'string' },
          reason: { type: 'string' }
        },
        required: ['skill', 'reason']
      }
    },
    noChangeNeeded: { type: 'array', items: { type: 'string' } }
  },
  required: ['skillsReviewed']
}

const REVIEW_SCHEMA = {
  type: 'object',
  properties: {
    findings: {
      type: 'array',
      items: {
        type: 'object',
        properties: {
          file: { type: 'string' },
          line: { type: 'number' },
          summary: { type: 'string' },
          failure_scenario: { type: 'string' },
          verdict: { type: 'string', enum: ['CONFIRMED', 'PLAUSIBLE'] }
        },
        required: ['file', 'summary', 'verdict']
      }
    }
  },
  required: ['findings']
}


let SPEC_FILE = null
let TASK_REF = null
let SPEC_DIR = 'spec/specs'

if (args && typeof args === 'object') {
  SPEC_FILE = args.specFile ?? null
  TASK_REF = args.taskRef ?? null
  if (args.specDir) SPEC_DIR = String(args.specDir).replace(/\/+$/, '')
} else if (typeof args === 'string') {
  const dirMatch = args.match(/(spec\/[\w-]+)\//)
  if (dirMatch) SPEC_DIR = dirMatch[1]
  const fileMatch = args.match(/(?:spec\/specs\/)?([\w-]+\.md)/)
  if (fileMatch) SPEC_FILE = fileMatch[1]
  const taskMatch = args.match(/\bRF\d+\b/i)
  if (taskMatch) TASK_REF = taskMatch[0].toUpperCase()
}

if (!SPEC_FILE) {
  throw new Error('No spec file resolved from args. Pass Workflow({ args: "spec/specs/00-architecture-database-and-guardrails.md" }) or { specFile, taskRef }. Refusing to silently default to a different spec.')
}

if (!TASK_REF) {
  TASK_REF = `the entirety of ${SPEC_FILE} (no single RF task ref given in args)`
}

const MAX_REVIEW_ITERATIONS = 6

log(`spec-task-implementer starting: ${SPEC_FILE} :: ${TASK_REF}`)

// ---------------------------------------------------------------------
// Phase 1: Understand the task
// ---------------------------------------------------------------------
phase('Understand')

const understanding = await agent(
  [
    `Read ${SPEC_DIR}/${SPEC_FILE} in full, plus spec/specs/00-architecture-database-and-guardrails.md and spec/specs/README.md for shared conventions.`,
    `Focus on the requirement/task: "${TASK_REF}".`,
    `Do NOT write or edit any code. This is a research-only pass.`,
    `Extract: the requirement text verbatim, every business rule (RN) tied to it, the DB tables/columns it touches (per spec 00 section 2.1), the acceptance criteria / checklist items from the spec, and cross-references to other spec files this task depends on.`
  ].join('\n'),
  { label: 'understand', phase: 'Understand', model: 'opus', schema: UNDERSTANDING_SCHEMA }
)

// ---------------------------------------------------------------------
// Phase 2: Tech-refine against the current codebase
// ---------------------------------------------------------------------
phase('Tech-Refine')

const techPlan = await agent(
  [
    `You are refining an implementation plan for task "${TASK_REF}" from ${SPEC_DIR}/${SPEC_FILE}.`,
    `Prior research on the requirement:`,
    JSON.stringify(understanding),
    `Study the CURRENT codebase state: existing migrations (database/migrations), models (app/Models), controllers/actions, routes, and any existing tests related to this task. Check what already exists vs. what is missing.`,
    `Follow the laravel-best-practices skill conventions while assessing fit (idiomatic Eloquent, policies, form requests, actions).`,
    `Produce a concrete technical plan split into EXACTLY 3 independent buckets of work suitable for 3 parallel coding agents (e.g. "migrations+models", "controllers/actions+routes", "Blade views+JS"), each bucket listing exact files to create/modify. List edge cases and open questions that would block implementation.`
  ].join('\n'),
  { label: 'tech-refine', phase: 'Tech-Refine', model: 'opus', schema: TECH_PLAN_SCHEMA }
)

const buckets = (techPlan?.buckets ?? []).slice(0, 3)

if (buckets.length === 0) {
  throw new Error('Tech-refine phase produced no implementation buckets — cannot proceed to Code phase.')
}

// ---------------------------------------------------------------------
// Phase 3: Code — up to 3 parallel coding agents, one per bucket
// ---------------------------------------------------------------------
phase('Code')

const codeResults = await parallel(
  buckets.map((bucket, i) => () =>
    agent(
      [
        `Implement this bucket only from the technical plan for "${TASK_REF}" (${SPEC_DIR}/${SPEC_FILE}):`,
        JSON.stringify(bucket),
        `Full plan context (for cross-bucket consistency, do not implement other buckets):`,
        JSON.stringify(techPlan),
        `Use the laravel-tdd skill's RED-GREEN-REFACTOR cycle for every piece of logic in this bucket, but written as PHPUnit test classes, NOT Pest functions — project CLAUDE.md mandates PHPUnit ("if you see a test using Pest, convert it to PHPUnit"), so ignore the Pest syntax in the laravel-tdd examples and translate the same cycle to PHPUnit: write a failing test method first (RED), confirm it fails with \`vendor/bin/sail artisan test --filter=testMethodName\`, write the minimal code to pass (GREEN), confirm it passes, then refactor (services/policies/scopes/events as appropriate) keeping tests green. View-only/config-only changes in this bucket are the only exception to writing a test first.`,
        `If this bucket touches Blade views, JS interactions, or any browser-facing flow (per spec/specs/00 §5's mandatory Dusk coverage), use the laravel-dusk skill: write the PHPUnit-style Browser test in tests/Browser (dusk selectors, explicit waitFor over pause, DatabaseMigrations or DatabaseTruncation trait — never RefreshDatabase in a Dusk test, since it runs in a separate HTTP process). Always prefix Dusk/artisan commands with \`vendor/bin/sail\` per project convention (the skill's own examples omit it).`,
        `Only touch the files listed for this bucket. Follow existing project conventions (CLAUDE.md, laravel-best-practices skill). Run \`vendor/bin/sail bin pint --dirty --format agent\` on any PHP files you touch before finishing.`
      ].join('\n'),
      { label: `code:${bucket.name ?? i + 1}`, phase: 'Code', model: 'opus' }
    )
  )
)

const codeOutputs = codeResults.filter(Boolean)

// ---------------------------------------------------------------------
// Phase 4: Verify the full suite (each bucket already wrote its own
// PHPUnit tests TDD-first in the Code phase per laravel-tdd, and Dusk
// browser tests per laravel-dusk for any UI-facing bucket)
// ---------------------------------------------------------------------
phase('Test')

const testResults = await agent(
  [
    `Task "${TASK_REF}" from ${SPEC_DIR}/${SPEC_FILE} was just implemented TDD-first across ${codeOutputs.length} bucket(s), each with its own PHPUnit tests written RED-before-GREEN per the laravel-tdd skill, and Dusk browser tests per laravel-dusk for UI-facing buckets. Summaries of what each bucket did:`,
    JSON.stringify(codeOutputs),
    `Run the laravel-tdd Verification Checklist: confirm migration tests pass, model relationships are tested, controller & API integration tests pass, validation and authorization are tested, database state is verified with RefreshDatabase for Feature/Unit tests, and factories were used.`,
    `Run the laravel-dusk checklist for any browser-facing bucket: Dusk tests use DatabaseMigrations/DatabaseTruncation (not RefreshDatabase), ChromeDriver is current (\`vendor/bin/sail artisan dusk:chrome-driver --detect\` if a version mismatch is reported), and failing tests leave a screenshot in tests/Browser/screenshots for debugging.`,
    `Also cover these edge cases from the tech-refine plan, adding any missing test if a bucket didn't already cover one: ${JSON.stringify(techPlan?.edgeCases ?? [])}.`,
    `Run \`vendor/bin/sail artisan test --compact\` for the FULL Unit/Feature suite (not just this task's tests, to catch regressions across buckets), then \`vendor/bin/sail artisan dusk\` for the Browser suite, then \`php scripts/check-coverage.php\` if present.`,
    `Report exact pass/fail counts per suite and coverage percentage. If anything fails, fix it and re-run before finishing.`
  ].join('\n'),
  { label: 'test', phase: 'Test', model: 'opus' }
)

// ---------------------------------------------------------------------
// Phase 5: code-reviewer loop — auto-fix CONFIRMED findings, capped
// ---------------------------------------------------------------------
phase('Review')

let lastReview = null
let iterations = 0

while (iterations < MAX_REVIEW_ITERATIONS && budget.remaining() > 0) {
  iterations++
  log(`code-reviewer pass ${iterations}/${MAX_REVIEW_ITERATIONS}`)

  lastReview = await agent(
    [
      `Review the uncommitted diff implementing task "${TASK_REF}" from ${SPEC_DIR}/${SPEC_FILE}.`,
      `Trigger the laravel-best-practices, laravel-specialist, and laravel-verification skills as your normal process dictates (static analysis, lint, test verification, architectural audit).`,
      `Trigger the validate-test-quality skill to audit all new/modified PHPUnit and Dusk tests against the 6 Pillars of Real Test Validation: SUT Integrity (0% SUT self-mocking), Assertion Meaningfulness (no tautological/weak factory checks), Mutation Resiliency, State Verification (database & response checks), Mandatory Fail-Path Testing (403, 422, exceptions, cross-tenant leaks), and Refactor Resilience. Any test quality defect, fake assertion, or missing failure path MUST be reported as a CONFIRMED finding.`,
      `Invoke the spec-usecase-test-checker agent to audit all Use Cases (UCs) defined for ${SPEC_DIR}/${SPEC_FILE} (in spec/docs/usecases/ and ${SPEC_DIR}/${SPEC_FILE}). Revalidate in code (tests/Browser/) that EVERY Use Case has AT LEAST ONE E2E Laravel Dusk test capturing all scenarios (success and failure/exception). Any missing Use Case Dusk test or missing scenario coverage MUST be reported as a CONFIRMED finding.`,
      `Test run summary from the previous phase:`,
      JSON.stringify(testResults),
      `Report findings ranked most-severe first, each with a clear verdict (CONFIRMED or PLAUSIBLE). Empty findings array if the diff is clean.`
    ].join('\n'),
    { label: `review:${iterations}`, phase: 'Review', model: 'opus', agentType: 'code-reviewer', schema: REVIEW_SCHEMA }
  )

  const confirmed = (lastReview?.findings ?? []).filter(f => f.verdict === 'CONFIRMED')

  if (confirmed.length === 0) {
    log('Review clean — no CONFIRMED findings. Stopping loop.')
    break
  }

  if (iterations >= MAX_REVIEW_ITERATIONS) {
    log(`Reached max review iterations (${MAX_REVIEW_ITERATIONS}) with unresolved findings. Stopping for human follow-up.`)
    break
  }

  await agent(
    [
      `The code-reviewer agent found these CONFIRMED issues in the implementation of "${TASK_REF}" (${SPEC_DIR}/${SPEC_FILE}):`,
      JSON.stringify(confirmed),
      `Fix every listed issue directly in the code. Re-run the relevant tests afterward to confirm nothing broke. Do not introduce new scope beyond fixing these findings.`
    ].join('\n'),
    { label: `fix:${iterations}`, phase: 'Review', model: 'opus' }
  )
}

log(`Done: ${SPEC_FILE} :: ${TASK_REF} — ${iterations} review iteration(s), ${(lastReview?.findings ?? []).filter(f => f.verdict === 'CONFIRMED').length} unresolved CONFIRMED finding(s).`)

// ---------------------------------------------------------------------
// Phase 6: Meta-Skill-Check — SPEC-03's auto-update protocol. Whenever
// code/schema that impacts a module changes, its skill triad
// (`{module}-architecture`, `{module}-conventions`, `{module}-maintenance`
// in .agents/skills/) must be checked for staleness and rewritten before
// the task is considered finished.
// ---------------------------------------------------------------------
phase('Meta-Skill-Check')

const skillCheck = await agent(
  [
    `Task "${TASK_REF}" from ${SPEC_DIR}/${SPEC_FILE} is implemented, tested, and reviewed clean. Final bucket summaries:`,
    JSON.stringify(codeOutputs),
    `Per spec/specs/03-agentic-harness-and-self-updating-skills.md and spec/specs/00-architecture-database-and-guardrails.md §6: every feature/module must maintain a triad of skills in .agents/skills/ — \`{module}-architecture\`, \`{module}-conventions\`, \`{module}-maintenance\` — and ANY code or schema change impacting a module must trigger a review/rewrite of its corresponding skills BEFORE the task is finalized.`,
    `1. Identify which module this task belongs to (derive from ${SPEC_FILE}'s filename, e.g. "quizzes", "certificates", "forum") and look for its 3 skills under .agents/skills/. If the triad doesn't exist yet, create it now, seeded from what was just built.`,
    `2. For each skill that exists, compare its documented architecture/conventions/maintenance notes against the code actually merged in this task (models, tables, actions, routes, business rules). Flag and fix anything now stale: renamed classes, changed table columns, new business rules not yet documented, removed patterns still described as current.`,
    `3. Separately check whether the project-level skills this task actually used — .agents/skills/laravel-tdd and .claude/skills/laravel-dusk — need a project-specific note added, ONLY if this task hit a real gap in them (e.g. a pattern not covered, an example that doesn't match this codebase). Do not rewrite these two skills wholesale — they are shared across all modules; add a narrow note only if truly needed.`,
    `Report which skills were reviewed, which were created, which were updated (with a one-line reason each), and which needed no change.`
  ].join('\n'),
  { label: 'meta-skill-check', phase: 'Meta-Skill-Check', model: 'opus', schema: SKILL_CHECK_SCHEMA }
)

return {
  specFile: SPEC_FILE,
  taskRef: TASK_REF,
  understanding,
  techPlan,
  codeOutputs,
  testResults,
  reviewIterations: iterations,
  finalReview: lastReview,
  skillCheck
}
