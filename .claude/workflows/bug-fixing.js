export const meta = {
  name: 'bug-fixing',
  description: 'Resolve a bug in spec/bugs/BUG-id-slug.md: understand it, then fix it TDD-first with a PHPUnit unit/integration test, or a Dusk test if it is a UI bug.',
  whenToUse: 'Run once per bug that has a specification file in spec/bugs/, passing the bug report path via args, for example bugReportFile equal to spec/bugs/BUG-001-quiz-score-calculation.md.',
  phases: [
    { title: 'Understand', detail: 'spec-understand-agent reads the BUG file and extracts reproduction steps, root cause hypothesis, and whether it is a UI bug. Research-only pass.', model: 'sonnet' },
    { title: 'TDD Fix', detail: 'spec-coder-agent writes the failing test, PHPUnit unit or integration, or Dusk if UI bug, confirms RED, applies the minimal fix for GREEN, then runs Pint.', model: 'sonnet' }
  ]
}

const BUG_UNDERSTANDING_SCHEMA = {
  type: 'object',
  properties: {
    bugId: { type: 'string' },
    summary: { type: 'string' },
    isUiBug: { type: 'boolean' },
    reproductionSteps: { type: 'array', items: { type: 'string' } },
    expectedBehavior: { type: 'string' },
    actualBehavior: { type: 'string' },
    mappedFiles: { type: 'array', items: { type: 'string' } },
    rootCauseHypothesis: { type: 'string' }
  },
  required: ['bugId', 'summary', 'isUiBug', 'reproductionSteps', 'expectedBehavior', 'actualBehavior']
}

const TDD_FIX_SCHEMA = {
  type: 'object',
  properties: {
    testType: { type: 'string', enum: ['PHPUnit', 'Dusk'] },
    testFile: { type: 'string' },
    testMethod: { type: 'string' },
    redObservedFailure: { type: 'string' },
    filesModified: { type: 'array', items: { type: 'string' } },
    fixSummary: { type: 'string' },
    testPasses: { type: 'boolean' }
  },
  required: ['testType', 'testFile', 'testMethod', 'redObservedFailure', 'filesModified', 'testPasses']
}

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

log(`bug-fixing starting: ${BUG_REPORT_FILE}`)

phase('Understand')

const understanding = await agent(
  [
    `Read ${BUG_REPORT_FILE} in FULL. This is the bug specification you must understand before anything is changed.`,
    `This is a RESEARCH-ONLY pass. Do NOT write, edit, or delete any code, test, or file.`,
    `Extract: the bug id, a one-paragraph summary, whether this is a UI bug (isUiBug: true only if the bug is a browser/visual/interaction issue that cannot be reproduced without a real browser), the step-by-step reproduction guide verbatim from the bug file, the expected behavior, the actual (buggy) behavior, the concrete files the bug maps to in the codebase (app/Http/Controllers, app/Models, app/Services, database/migrations, tests/), and your root cause hypothesis.`,
    `If the bug file omits something, say so explicitly rather than inventing it.`
  ].join('\n'),
  { label: 'understand', phase: 'Understand', model: 'sonnet', agentType: 'spec-understand-agent', schema: BUG_UNDERSTANDING_SCHEMA }
)

phase('TDD Fix')

const fix = await agent(
  [
    `Fix the bug from ${BUG_REPORT_FILE} using a strict TDD RED-GREEN-REFACTOR cycle. Never write fix code before you have watched the reproduction test fail.`,
    `Bug analysis:`,
    JSON.stringify(understanding),
    `Choose the test type yourself: use a Dusk browser test ONLY if isUiBug is true and the bug cannot be reproduced without a real browser; otherwise write a PHPUnit unit or integration test (whichever fits the bug — a Unit test for isolated logic, a Feature/integration test for anything touching HTTP, the database, or multiple collaborators).`,
    `STEP 1 — RED. Write the reproduction test reproducing the exact steps from the bug file: ${JSON.stringify(understanding?.reproductionSteps ?? [])}. ${understanding?.isUiBug ? 'Dusk tests extend DuskTestCase and MUST use DatabaseMigrations or DatabaseTruncation — never RefreshDatabase, because Dusk runs in a separate HTTP process. Use dusk selectors and explicit waitFor calls over pause.' : 'PHPUnit tests extend Tests\\TestCase and use RefreshDatabase with model factories.'} Use PHPUnit test classes, never Pest functions, per project CLAUDE.md. Run the test with \`vendor/bin/sail artisan test --filter={method}\` (or \`vendor/bin/sail artisan dusk --filter={method}\` for Dusk) and VERIFY IT FAILS for the right reason — the asserted behavioral failure, not a syntax error, missing class, or setup error. If it fails for a setup reason, fix the test until the failure is genuine.`,
    `STEP 2 — GREEN. Write the MINIMAL code to resolve the root cause. No refactors, no extra features, no unrelated cleanup. Re-run the same filtered test command and VERIFY IT PASSES completely.`,
    `STEP 3 — REFACTOR. Remove any temporary debug lines while keeping the test green, respecting tenancy rules (OrgScope, org_id filtering) so the fix cannot leak data across tenants. Then run \`vendor/bin/sail bin pint --dirty --format agent\` to format every PHP file you touched.`,
    `All commands must be prefixed with \`vendor/bin/sail\`. Report exactly what you saw in RED, the fix you made, and the final test result.`
  ].join('\n'),
  { label: 'tdd-fix', phase: 'TDD Fix', model: 'sonnet', agentType: 'spec-coder-agent', schema: TDD_FIX_SCHEMA }
)

log(`bug-fixing done: ${fix?.testType} test ${fix?.testFile}::${fix?.testMethod}, passes=${fix?.testPasses}`)

return {
  bugReportFile: BUG_REPORT_FILE,
  understanding,
  fix
}
