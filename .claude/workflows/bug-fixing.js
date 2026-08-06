export const meta = {
  name: 'bug-fixing',
  description: 'Resolve a bug specified in spec/bugs/BUG-{id}-{slug}.md end-to-end: analyze the bug report, tech-refine a TDD reproduction plan against the codebase, run the RED-GREEN-REFACTOR fix cycle with PHPUnit/Dusk under Sail, verify zero regressions across the full suite, loop code-reviewer + validate-test-quality with spec-fixer-agent until clean, then sign the bug off as RESOLVED and update the module skill triad per SPEC-03.',
  whenToUse: 'Run once per bug that has a specification file in spec/bugs/, passing the bug report path via args, for example { bugReportFile: "spec/bugs/BUG-001-quiz-score-calculation.md" }.',
  phases: [
    { title: 'Bug Analysis', detail: 'spec-understand-agent reads the BUG file in full and extracts reproduction steps, roles, tenant context and root cause hypothesis. Research-only pass.', model: 'sonnet' },
    { title: 'Tech-Refine', detail: 'spec-tech-refine-agent inspects the mapped files and produces the TDD reproduction plan: test type, test file/method, minimal fix scope, regression risk scopes.', model: 'sonnet' },
    { title: 'TDD Fix Cycle', detail: 'spec-coder-agent writes the failing reproduction test in RED, applies the minimal fix in GREEN, then refactors and runs Pint.', model: 'sonnet' },
    { title: 'Test & Regression', detail: 'spec-tester-agent reruns the reproduction test, the full PHPUnit suite and the full Dusk suite always, demanding 100% pass.', model: 'sonnet' },
    { title: 'Review', detail: 'code-reviewer and validate-test-quality run in parallel each round, then spec-fixer-agent repairs CONFIRMED findings and tests are re-verified. Capped at 6 iterations.', model: 'sonnet' },
    { title: 'Sign-off', detail: 'spec-meta-skill-checker-agent marks the BUG file RESOLVED and audits the module skill triad, adding a maintenance gotcha when the root cause exposed an architectural edge case.', model: 'sonnet' }
  ]
}


const BUG_UNDERSTANDING_SCHEMA = {
  type: 'object',
  properties: {
    bugId: { type: 'string' },
    summary: { type: 'string' },
    affectedRole: { type: 'string' },
    tenantContext: { type: 'string' },
    reproductionSteps: { type: 'array', items: { type: 'string' } },
    expectedBehavior: { type: 'string' },
    actualBehavior: { type: 'string' },
    mappedFiles: { type: 'array', items: { type: 'string' } },
    rootCauseHypothesis: { type: 'string' }
  },
  required: ['bugId', 'summary', 'reproductionSteps', 'expectedBehavior', 'actualBehavior']
}

const BUG_FIX_PLAN_SCHEMA = {
  type: 'object',
  properties: {
    testType: { type: 'string', enum: ['PHPUnit', 'Dusk', 'Both'] },
    reproductionTestFile: { type: 'string' },
    reproductionTestMethod: { type: 'string' },
    targetFixFiles: { type: 'array', items: { type: 'string' } },
    regressionRiskScopes: { type: 'array', items: { type: 'string' } }
  },
  required: ['testType', 'reproductionTestFile', 'reproductionTestMethod', 'targetFixFiles']
}

const TDD_CYCLE_SCHEMA = {
  type: 'object',
  properties: {
    redPhase: {
      type: 'object',
      properties: {
        testWritten: { type: 'string' },
        observedFailure: { type: 'string' },
        confirmedGenuineFailure: { type: 'boolean' }
      },
      required: ['testWritten', 'observedFailure', 'confirmedGenuineFailure']
    },
    greenPhase: {
      type: 'object',
      properties: {
        filesModified: { type: 'array', items: { type: 'string' } },
        fixSummary: { type: 'string' },
        testPasses: { type: 'boolean' }
      },
      required: ['filesModified', 'fixSummary', 'testPasses']
    },
    refactorPhase: {
      type: 'object',
      properties: {
        cleanupSummary: { type: 'string' },
        pintRun: { type: 'boolean' }
      },
      required: ['pintRun']
    }
  },
  required: ['redPhase', 'greenPhase']
}

const REGRESSION_SCHEMA = {
  type: 'object',
  properties: {
    reproductionTestPassed: { type: 'boolean' },
    phpunitPassed: { type: 'number' },
    phpunitFailed: { type: 'number' },
    duskRun: { type: 'boolean' },
    duskPassed: { type: 'number' },
    duskFailed: { type: 'number' },
    allGreen: { type: 'boolean' },
    brokenPreExistingTests: { type: 'array', items: { type: 'string' } },
    notes: { type: 'string' }
  },
  required: ['reproductionTestPassed', 'allGreen']
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

const SIGN_OFF_SCHEMA = {
  type: 'object',
  properties: {
    bugFileUpdated: { type: 'boolean' },
    resolutionStatus: { type: 'string' },
    reproductionTest: { type: 'string' },
    fixedInFiles: { type: 'array', items: { type: 'string' } },
    module: { type: 'string' },
    skillsReviewed: { type: 'array', items: { type: 'string' } },
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
    maintenanceGotchaAdded: { type: 'string' }
  },
  required: ['bugFileUpdated', 'skillsReviewed']
}


// ---------------------------------------------------------------------
// Args resolution: the bug report file is mandatory. Never guess a bug.
// ---------------------------------------------------------------------
let BUG_REPORT_FILE = null

if (args && typeof args === 'object') {
  BUG_REPORT_FILE = args.bugReportFile ?? args.bugFile ?? null
} else if (typeof args === 'string') {
  const match = args.match(/(?:spec\/bugs\/)?(BUG-[\w-]+\.md)/i)
  if (match) BUG_REPORT_FILE = match[1]
}

if (!BUG_REPORT_FILE) {
  throw new Error('No bug report file resolved from args. Pass Workflow({ args: { bugReportFile: "spec/bugs/BUG-001-quiz-score-calculation.md" } }) or a string containing the BUG-*.md filename. Refusing to guess which bug to fix.')
}

if (!BUG_REPORT_FILE.includes('/')) {
  BUG_REPORT_FILE = `spec/bugs/${BUG_REPORT_FILE}`
}

const MAX_REVIEW_ITERATIONS = 6

log(`bug-fixing starting: ${BUG_REPORT_FILE}`)

// ---------------------------------------------------------------------
// Phase 1: Bug Analysis — research-only read of the bug specification
// ---------------------------------------------------------------------
phase('Bug Analysis')

const understanding = await agent(
  [
    `Read ${BUG_REPORT_FILE} in FULL. This is the bug specification you must understand before anything is changed.`,
    `Also read the related feature specs in spec/specs/ that cover the affected module, plus spec/specs/00-architecture-database-and-guardrails.md for the shared architecture, database and tenancy guardrails.`,
    `This is a RESEARCH-ONLY pass. Do NOT write, edit, or delete any code, test, or file.`,
    `Extract: the bug id, a one-paragraph summary, the affected role (e.g. student, instructor, org admin), the tenant context (which org_id / OrgScope boundary is involved), the step-by-step reproduction guide verbatim from the bug file, the expected behavior, the actual (buggy) behavior, the concrete files the bug maps to in the codebase (app/Http/Controllers, app/Models, app/Services, database/migrations, tests/), and your root cause hypothesis.`,
    `If the bug file omits something, say so explicitly rather than inventing it.`
  ].join('\n'),
  { label: 'bug-analysis', phase: 'Bug Analysis', model: 'sonnet', agentType: 'spec-understand-agent', schema: BUG_UNDERSTANDING_SCHEMA }
)

// ---------------------------------------------------------------------
// Phase 2: Tech-Refine — design the TDD reproduction + minimal fix plan
// ---------------------------------------------------------------------
phase('Tech-Refine')

const fixPlan = await agent(
  [
    `You are designing the TDD fix plan for the bug described in ${BUG_REPORT_FILE}.`,
    `Analysis from the previous phase:`,
    JSON.stringify(understanding),
    `Inspect the CURRENT codebase at the mapped files and their neighbours: app/Http/Controllers, app/Models, app/Services, database/migrations, and tests/ (both tests/Feature and tests/Browser). Confirm or correct the root cause hypothesis against the real code.`,
    `Produce a precise plan with three parts:`,
    `1. Reproduction Test Target — decide testType: "PHPUnit" for server-side/logic bugs, "Dusk" for browser/UI-only bugs, "Both" when the bug spans both. Give the EXACT test file path to create or extend (tests/Feature/... or tests/Browser/...) and the EXACT test method name (snake_case test_ method per project PHPUnit convention).`,
    `2. Minimal Fix Scope — the exact files to modify to fix the root cause, with no scope creep and no side effects. Prefer the narrowest change that removes the root cause, not a symptom patch.`,
    `3. Regression Risk Scopes — related modules, roles, or tenant boundaries (OrgScope, org_id filtering) that this fix could plausibly affect and that the test phase must therefore re-verify.`,
    `Do not implement anything yet. Plan only.`
  ].join('\n'),
  { label: 'tech-refine', phase: 'Tech-Refine', model: 'sonnet', agentType: 'spec-tech-refine-agent', schema: BUG_FIX_PLAN_SCHEMA }
)

if (!fixPlan?.reproductionTestMethod || !fixPlan?.reproductionTestFile) {
  throw new Error('Tech-refine phase produced no reproduction test target — cannot enter the TDD cycle without a failing test to write first.')
}

const testType = fixPlan.testType ?? 'PHPUnit'
const testMethod = fixPlan.reproductionTestMethod
const isDusk = testType === 'Dusk' || testType === 'Both'
const testCommand = testType === 'Dusk'
  ? `vendor/bin/sail artisan dusk --filter=${testMethod}`
  : `vendor/bin/sail artisan test --filter=${testMethod}`

log(`Fix plan ready: ${testType} test ${fixPlan.reproductionTestFile}::${testMethod}, ${(fixPlan.targetFixFiles ?? []).length} target fix file(s).`)

// ---------------------------------------------------------------------
// Phase 3: TDD Fix Cycle — RED, then GREEN, then REFACTOR
// ---------------------------------------------------------------------
phase('TDD Fix Cycle')

const tddCycle = await agent(
  [
    `Fix the bug from ${BUG_REPORT_FILE} using a strict TDD RED-GREEN-REFACTOR cycle. Never write fix code before you have watched the reproduction test fail.`,
    `Bug analysis:`,
    JSON.stringify(understanding),
    `Approved fix plan:`,
    JSON.stringify(fixPlan),
    `STEP 1 — RED. Write the reproduction test at ${fixPlan.reproductionTestFile}, method ${testMethod}. ${isDusk ? 'Dusk browser tests extend DuskTestCase and MUST use DatabaseMigrations or DatabaseTruncation — never RefreshDatabase, because Dusk runs in a separate HTTP process. Use dusk selectors and explicit waitFor calls over pause.' : 'PHPUnit tests extend Tests\\TestCase and use RefreshDatabase with model factories.'} Use PHPUnit test classes, never Pest functions, per project CLAUDE.md. The test must reproduce the exact failure steps from the bug file: ${JSON.stringify(understanding?.reproductionSteps ?? [])}. Then run \`${testCommand}\` and VERIFY IT FAILS for the right reason — the asserted behavioral failure, not a syntax error, missing class, or setup error. If it fails for a setup reason, fix the test until the failure is genuine.`,
    `STEP 2 — GREEN. Write the MINIMAL code in ${JSON.stringify(fixPlan.targetFixFiles ?? [])} to resolve the root cause. No refactors, no extra features, no unrelated cleanup. Re-run \`${testCommand}\` and VERIFY IT PASSES completely.`,
    `STEP 3 — REFACTOR. Remove any temporary debug lines and code smells while keeping the test green, respecting tenancy rules (OrgScope, org_id filtering) so the fix cannot leak data across tenants. Then run \`vendor/bin/sail bin pint --dirty --format agent\` to format every PHP file you touched.`,
    `All commands must be prefixed with \`vendor/bin/sail\`. Report exactly what you saw at each step, including the literal failure message observed in RED.`
  ].join('\n'),
  { label: 'tdd-cycle', phase: 'TDD Fix Cycle', model: 'sonnet', agentType: 'spec-coder-agent', schema: TDD_CYCLE_SCHEMA }
)

if (tddCycle?.redPhase?.confirmedGenuineFailure === false) {
  log('WARNING: the coder agent could not confirm a genuine RED failure. The reproduction test may not actually exercise the bug — the review phase must scrutinise it.')
}

// ---------------------------------------------------------------------
// Phase 4: Test & Regression — full suite, zero regressions
// ---------------------------------------------------------------------
phase('Test & Regression')

const regression = await agent(
  [
    `The bug from ${BUG_REPORT_FILE} has just been fixed. Verify the fix and prove there are zero regressions across tenant, role, and feature boundaries.`,
    `TDD cycle report:`,
    JSON.stringify(tddCycle),
    `Regression risk scopes flagged during planning: ${JSON.stringify(fixPlan.regressionRiskScopes ?? [])}.`,
    `Run, in order:`,
    `1. The reproduction test: \`${testCommand}\` — it must pass.`,
    `2. The full Unit/Feature suite: \`vendor/bin/sail artisan test --compact\` — every test must pass.`,
    `3. The full browser suite, always, regardless of bug type: \`vendor/bin/sail artisan dusk:chrome-driver --detect\` then \`vendor/bin/sail artisan dusk\` — every test must pass.`,
    `You must reach 100% pass. If a PRE-EXISTING test broke, the fix violated an existing contract: adjust the FIX to preserve that contract. Never weaken, skip, or delete an existing test to make the suite green.`,
    `Report exact pass/fail counts per suite and name any pre-existing test that broke along with how you resolved it.`
  ].join('\n'),
  { label: 'regression', phase: 'Test & Regression', model: 'sonnet', agentType: 'spec-tester-agent', schema: REGRESSION_SCHEMA }
)

if (regression?.allGreen === false) {
  log('WARNING: the regression phase did not reach a fully green suite. Review will run anyway, but the run should not be treated as complete until the suite is green.')
}

// ---------------------------------------------------------------------
// Phase 5: Review loop — code-reviewer and validate-test-quality run in
// parallel each round (they audit different things and must both finish
// before the fixer decides), then spec-fixer-agent repairs CONFIRMED
// findings and tests are re-verified. Capped at MAX_REVIEW_ITERATIONS.
// ---------------------------------------------------------------------
phase('Review')

let iterations = 0
let confirmedFindings = []
let lastCodeReview = null
let lastTestReview = null

while (iterations < MAX_REVIEW_ITERATIONS && budget.remaining() > 0) {
  iterations++
  log(`Review pass ${iterations}/${MAX_REVIEW_ITERATIONS}`)

  const [codeReview, testReview] = await parallel([
    () => agent(
      [
        `Review the uncommitted diff that fixes the bug described in ${BUG_REPORT_FILE}.`,
        `Bug analysis:`,
        JSON.stringify(understanding),
        `Fix plan and files touched:`,
        JSON.stringify(fixPlan),
        `Audit against the laravel-best-practices skill (idiomatic Eloquent, policies, form requests, service/action boundaries) and against tenancy-security: no cross-tenant data leaks, correct OrgScope application, org_id filtering on every query the fix touches or bypasses.`,
        `Also confirm the fix addresses the ROOT CAUSE rather than masking a symptom, and that it stayed inside the approved minimal fix scope without scope creep.`,
        `Regression run summary:`,
        JSON.stringify(regression),
        `Report findings ranked most-severe first, each with verdict CONFIRMED or PLAUSIBLE. Return an empty findings array if the diff is clean.`
      ].join('\n'),
      { label: `code-review:${iterations}`, phase: 'Review', model: 'sonnet', agentType: 'code-reviewer', schema: REVIEW_SCHEMA }
    ),
    () => agent(
      [
        `Apply the validate-test-quality skill to the reproduction test ${fixPlan.reproductionTestFile} (method ${testMethod}) and to any other test touched while fixing the bug in ${BUG_REPORT_FILE}.`,
        `TDD cycle report, including the failure observed in the RED phase:`,
        JSON.stringify(tddCycle),
        `Audit against the 6 Pillars of Real Test Validation: SUT Integrity (0% self-mocking of the system under test), Assertion Meaningfulness (no tautological or weak factory-echo assertions), Mutation Resiliency (reverting the fix MUST make this test fail again), State Verification (real database assertions, not just response status), Mandatory Fail-Path Testing (403/422, exceptions, cross-tenant leak attempts), and Refactor Resilience.`,
        `The decisive question: does this test actually reproduce the reported bug, such that it would have caught it before the fix existed? Any fake assertion, missing failure path, or test that would pass even without the fix MUST be reported as a CONFIRMED finding.`,
        `Report findings with verdict CONFIRMED or PLAUSIBLE. Return an empty findings array if the tests are sound.`
      ].join('\n'),
      { label: `test-quality:${iterations}`, phase: 'Review', model: 'sonnet', agentType: 'code-reviewer', schema: REVIEW_SCHEMA }
    )
  ])

  lastCodeReview = codeReview
  lastTestReview = testReview

  const allFindings = [
    ...(codeReview?.findings ?? []),
    ...(testReview?.findings ?? [])
  ]
  confirmedFindings = allFindings.filter(f => f.verdict === 'CONFIRMED')

  if (confirmedFindings.length === 0) {
    log(`Review clean on pass ${iterations} — no CONFIRMED findings from either reviewer. Stopping loop.`)
    break
  }

  if (iterations >= MAX_REVIEW_ITERATIONS) {
    log(`Reached max review iterations (${MAX_REVIEW_ITERATIONS}) with ${confirmedFindings.length} unresolved CONFIRMED finding(s). Stopping for human follow-up.`)
    break
  }

  log(`${confirmedFindings.length} CONFIRMED finding(s) — dispatching spec-fixer-agent.`)

  await agent(
    [
      `The review agents found these CONFIRMED issues in the fix for the bug in ${BUG_REPORT_FILE}:`,
      JSON.stringify(confirmedFindings),
      `Fix every listed issue directly in the code or tests. Do not introduce new scope beyond resolving these findings, and do not weaken or delete a test to silence a finding.`,
      `After applying the fixes, re-verify: run \`${testCommand}\` for the reproduction test, \`vendor/bin/sail artisan test --compact\` for the full suite, and \`vendor/bin/sail artisan dusk\` for the full browser suite. All must be fully green before you finish.`,
      `Run \`vendor/bin/sail bin pint --dirty --format agent\` on any PHP file you touched.`
    ].join('\n'),
    { label: `fix:${iterations}`, phase: 'Review', model: 'sonnet', agentType: 'spec-fixer-agent' }
  )
}

log(`Review complete: ${iterations} iteration(s), ${confirmedFindings.length} unresolved CONFIRMED finding(s).`)

// ---------------------------------------------------------------------
// Phase 6: Sign-off — mark the bug RESOLVED and keep module skills honest
// ---------------------------------------------------------------------
phase('Sign-off')

const signOff = await agent(
  [
    `The bug described in ${BUG_REPORT_FILE} is fixed, tested and reviewed. Sign it off and update the module documentation per SPEC-03.`,
    `Bug analysis:`,
    JSON.stringify(understanding),
    `Fix plan:`,
    JSON.stringify(fixPlan),
    `Regression results:`,
    JSON.stringify(regression),
    `Unresolved CONFIRMED findings at the end of the review loop (${confirmedFindings.length}): ${JSON.stringify(confirmedFindings)}. If this list is non-empty, record the bug as RESOLVED-WITH-CAVEATS instead of RESOLVED and document each open finding in the bug file so a human can pick it up.`,
    `1. Update ${BUG_REPORT_FILE} with a Resolution Status section: status, the reproduction test path and method (\`${fixPlan.reproductionTestFile}\` :: \`${testMethod}\`), and the list of files the fix touched.`,
    `2. Identify which module this bug belongs to and audit its skill triad in .agents/skills/: {module}-architecture, {module}-conventions, {module}-maintenance. Per spec/specs/03-agentic-harness-and-self-updating-skills.md and spec/specs/00-architecture-database-and-guardrails.md section 6, any code or schema change impacting a module must trigger a review of its skills before the work is finalized. Fix anything now stale: renamed classes, changed columns, rules the fix altered.`,
    `3. If the root cause revealed an architectural edge case or a maintenance gotcha — a trap the next developer would fall into the same way — add it explicitly to {module}-maintenance/SKILL.md so this class of bug cannot recur. If the root cause was a one-off typo with no generalizable lesson, say so and add nothing.`,
    `Report what you updated and why.`
  ].join('\n'),
  { label: 'sign-off', phase: 'Sign-off', model: 'sonnet', agentType: 'spec-meta-skill-checker-agent', schema: SIGN_OFF_SCHEMA }
)

return {
  bugReportFile: BUG_REPORT_FILE,
  understanding,
  fixPlan,
  tddCycle,
  regression,
  reviewIterations: iterations,
  unresolvedConfirmedFindings: confirmedFindings,
  lastCodeReview,
  lastTestReview,
  signOff
}
