---
name: create-pull-request
description: >-
  Use when asked to create GitHub Pull Request (PR), generate PR description
  from code changes, or open PR for specified files and feature intent.
---

# Create Pull Request

## Overview

Good Pull Request combine **task intent** with **exact inspection of code changes (diff)**. This skill guide step-by-step: study changes, analyze technical and architectural impact, write reviewer-focused PR description, run `gh pr create` safely.

## When to Use

- User asks to open/create GitHub Pull Request for changed files.
- Given specific files plus task intent to submit for code review.
- Preparing PR description needing structured technical breakdown and reviewer guidance.

---

## Workflow Steps

```
┌───────────────────────────────────────────────────────────┐
│ 1. Parse Arguments (Intent + Target Files)               │
└─────────────────────────────┬─────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────┐
│ 2. Inspect Code Changes (git diff / file view)            │
└─────────────────────────────┬─────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────┐
│ 3. Verify Git State & Push Remote Branch                 │
└─────────────────────────────┬─────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────┐
│ 4. Build Detailed PR Description (Markdown File)          │
└─────────────────────────────┬─────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────┐
│ 5. Execute `gh pr create --body-file`                    │
└─────────────────────────────┬─────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────┐
│ 6. Verify Output & Return PR Link                        │
└───────────────────────────────────────────────────────────┘
```

---

### Step 1: Parse Inputs & Inspect Code Changes

1. **Identify Task Intent**: Review provided task objective (issue description, feature requirement, or bug fix goal).
2. **Inspect Changed Files**:
   - Run `git status` or inspect provided file list.
   - **MANDATORY**: Run `git diff HEAD -- <files>`, or read files directly, to inspect exact changes line-by-line.
   - Do NOT summarize from assumptions or high-level descriptions alone; verify actual code edits.

```bash
# Example diff command for specified files
git diff HEAD -- app/Models/User.php tests/Feature/TenantUserTest.php
```

---

### Step 2: Verify Git & Remote Branch State

Before running `gh pr create`, make repository and branch states ready:

1. **Branch Check**: Verify current branch not `main`/`master` (`git branch --show-current`).
2. **Uncommitted Changes**: All target changes committed.
3. **Push to Remote**: Local branch pushed to remote origin.

```bash
# Push branch to remote if not already pushed
git push -u origin $(git branch --show-current)
```

---

### Step 3: Build the PR Description

Write PR description to temp file (`/tmp/pr_description.md`) to avoid bash escaping corruption.

#### PR Description Template

```markdown
## 🎯 Intent & Context
- **Objective**: <Brief summary of what this PR accomplishes>
- **Task / Issue Reference**: <Reference issue/task ID if provided, e.g., #123>
- **Motivation**: <Why this change is needed and what problem it solves>

## Technical Summary & Key Changes
- **`<file_1_path>`**: <Technical description of changes in this file>
- **`<file_2_path>`**: <Technical description of changes in this file>

## 🛡️ Security, Tenancy & Architectural Impact
- **Multi-tenancy / Scope**: <Tenant isolation impact, org_id scoping, or N/A>
- **Database / Schema**: <Migrations, indexing, or database impact>
- **Breaking Changes**: <None or explicit list of breaking changes and migration steps>

## 🧪 Verification & Test Coverage
- **Executed Tests**: `<Command run, e.g., vendor/bin/sail artisan test tests/Feature/TenantUserTest.php>`
- **Results**: `<Passing status and test count>`
- **Code Style / Linting**: `<Status of vendor/bin/sail bin pint --dirty or equivalent>`

## 👁️ Reviewer Focus & Edge Cases
- **Key Files to Review**: `<Specific files or functions requiring close inspection>`
- **Edge Cases Considered**: `<Handled edge cases, null checks, exception paths>`

## ✅ Reviewer Checklist
- [ ] Code follows project conventions and architecture standards.
- [ ] Multi-tenancy boundaries (`org_id` / scopes) are correctly preserved.
- [ ] Tests cover happy path, failure paths, and edge cases.
- [ ] No extraneous debug logs or temporary code included.
```

---

### Step 4: Execute `gh pr create`

Run `gh pr create` with `--body-file` pointing at temp markdown file:

```bash
# Write body to temp file
cat << 'EOF' > /tmp/pr_description.md
<PR_DESCRIPTION_CONTENT>
EOF

# Create PR via GitHub CLI
gh pr create \
  --title "<type>(<scope>): <short imperative title>" \
  --body-file /tmp/pr_description.md

# Cleanup temp file
rm /tmp/pr_description.md
```

#### PR Title Formatting
Use Conventional Commits format:
- `feat(tenancy): add multi-tenancy filter to User model`
- `fix(auth): resolve session invalidation bug on tenant switch`
- `refactor(courses): optimize eager loading for module lessons`

---

### Step 5: Post-Creation Verification

1. Verify `gh pr create` exited with code `0`.
2. Extract and report generated Pull Request URL (e.g., `https://github.com/org/repo/pull/42`).
3. Give short confirmation summary to user.

---

## Rationalizations & Red Flags

| Rationalization / Shortcut | Why It Fails | Mandatory Rule |
| :--- | :--- | :--- |
| *"I already know what changed from the prompt, no need to run `git diff`."* | Misses subtle bugs, uncommitted edits, or missing test assertions. | **Always inspect the actual code diff** before writing the PR description. |
| *"I can inline the `--body` flag in `gh pr create` using a multiline string."* | Bash quote expansion breaks backticks, code blocks, and markdown formatting. | **Always write description to `--body-file`**. |
| *"I'll create the PR first, then push the branch."* | `gh pr create` will fail or attempt to compare against un-pushed commits. | **Push branch to origin before creating PR**. |
| *"A brief 2-line summary is enough for the description."* | Reviewers lack context on testing, security impact, breaking changes, and reviewer focus points. | **Always use the full structured PR description template**. |

---

## Checklist Before Finishing

- [ ] Inspected diff of all specified files (`git diff`).
- [ ] Verified git branch is clean and pushed to remote origin.
- [ ] Structured complete PR description covering Intent, Changes, Impact, Testing, Reviewer Guidance, and Checklist.
- [ ] Executed `gh pr create` using `--body-file`.
- [ ] Outputted the resulting PR link to the user.
