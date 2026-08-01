---
name: tenancy-security
description: >
    Use when reviewing or writing Laravel code, Eloquent queries, controllers,
    policies, Form Requests, or database operations to audit single-database multitenant
    isolation, prevent cross-tenant data leaks, and enforce role-based access control
    between Admin, Gestor, and Aluno roles.
license: MIT
metadata:
    feature: tenancy
    role: security-auditor
    specs:
        - spec/specs/00-architecture-database-and-guardrails.md
        - spec/docs/multitenancy.md
---

# Tenancy Security & Anti-Data-Leakage Skill

## Overview

The platform uses a **single MySQL database multitenancy architecture**. Tenant isolation is enforced at the **application layer** via `org_id` foreign keys, the `OrgScope` Eloquent global scope trait, and `RolesEnum` authorization.

Because isolation is not enforced by database row-level security (RLS) or separate databases, **a single missing tenant check or un-scoped query will leak data across organizations.**

---

## The Core Tenancy Security Model

### The 3 Roles & Data Scoping Rules

| Role (`RolesEnum`) | User `org_id`  | Scope of Access & Security Boundary                                                                                                                                                      |
| ------------------ | -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `admin`            | `null`         | Global access by default. Can switch context to a single organization via Impersonate Org (`session('active_org_id')`).                                                                  |
| `gestor`           | Fixed `org_id` | Restricted **strictly** to data matching their `user->org_id`. Cannot read or write data from any other `org_id`.                                                                        |
| `aluno`            | Usually `null` | Enrolled across courses in multiple orgs via `course_user` pivot. Must **never** access org-scoped data directly; access is granted **only** through active course enrollment relations. |

### Model Classifications & Scoping Guardrails

1. **Directly Org-Scoped Models** (Has `org_id` column + `OrgScope` trait):
    - Examples: `Course`, `InvitationLink`, `ForumTopic`, `HelpArticle` (nullable), `SystemSetting` (nullable).
    - _Security Guardrail_: Queries automatically append `where org_id = ?`. Bypassing `OrgScope` is a critical security vulnerability.

2. **Cascade-Inherited Models** (No `org_id` column; inherited through parent):
    - Examples: `Module`, `Lesson`, `Quiz`, `QuizQuestion`, `QuizOption`, `Certificate`.
    - _Security Guardrail_: Do NOT have `OrgScope` applied. **Finding by primary key directly (`Lesson::find($id)`) allows cross-tenant ID guessing unless explicitly scoped through parent relation!**

3. **Global / Non-Org-Scoped Models**:
    - Examples: `User` (has `org_id` column but **NO `OrgScope` trait**), `Notification`.
    - _Security Guardrail_: `User::all()` returns users from ALL tenants. Controllers must explicitly filter `User::where('org_id', $orgId)` when executing Gestor actions.

---

## 🚨 Critical Security Vulnerabilities & Prevention Rules

### 1. Raw SQL & DB Query Builder Bypasses (`DB::table(...)`)

**Vulnerability**: Using `DB::table('courses')` bypasses Eloquent's `OrgScope` global scope completely.

```php
// ❌ DANGEROUS: Bypasses OrgScope completely! Returns courses from ALL tenants.
$courses = DB::table('courses')->where('is_published', true)->get();
```

```php
// ✅ SAFE: Eloquent automatically applies OrgScope
$courses = Course::where('is_published', true)->get();

// ✅ SAFE (if raw DB builder is mandatory): Explicitly filter by resolved active org_id
$orgId = auth()->user()->org_id ?? session('active_org_id');
$courses = DB::table('courses')
    ->where('org_id', $orgId)
    ->where('is_published', true)
    ->get();
```

---

### 2. Unscoped `User` Model Queries (Cross-Tenant User Leak)

**Vulnerability**: `User` model does **NOT** have `OrgScope` applied (because Admins and Alunos have `org_id = null`). Querying `User` directly in a Gestor controller leaks users from other organizations.

```php
// ❌ DANGEROUS: A Gestor calling this sees users from all organizations!
$users = User::where('name', 'like', "%{$search}%")->get();
```

```php
// ✅ SAFE: Always scope User queries by active tenant in Gestor context
$gestorOrgId = auth()->user()->org_id;

$users = User::where('org_id', $gestorOrgId)
    ->where('name', 'like', "%{$search}%")
    ->get();
```

---

### 3. Cascade-Inherited Model Access (ID Guessing Attack)

**Vulnerability**: Models like `Lesson` or `Quiz` have no `OrgScope`. Calling `Lesson::findOrFail($id)` lets a Gestor from Tenant A view/edit a Lesson ID belonging to Tenant B.

```php
// ❌ DANGEROUS: No tenant check on cascade-inherited model!
public function show(int $id)
{
    $lesson = Lesson::findOrFail($id); // Insecure! ID guessing allows tenant leak.
    return view('lessons.show', compact('lesson'));
}
```

```php
// ✅ SAFE Option A: Scope query through parent relation chain
public function show(int $id)
{
    $orgId = auth()->user()->org_id ?? session('active_org_id');

    $lesson = Lesson::whereHas('module.course', function ($query) use ($orgId) {
        $query->where('org_id', $orgId);
    })->findOrFail($id);

    return view('lessons.show', compact('lesson'));
}

// ✅ SAFE Option B: Authorize using Policy that checks tenant ownership
public function show(Lesson $lesson)
{
    $this->authorize('view', $lesson); // LessonPolicy checks $lesson->module->course->org_id === $user->org_id
    return view('lessons.show', compact('lesson'));
}
```

---

### 4. Global Scope Removal (`withoutGlobalScope` / `withoutGlobalScopes`)

**Vulnerability**: Removing global scopes exposes data across all tenants.

```php
// ❌ DANGEROUS: Removes tenant isolation completely!
$course = Course::withoutGlobalScope(OrgScope::class)->find($id);
```

**Rule**: `withoutGlobalScope(OrgScope::class)` is **strictly prohibited** in standard application flows. It is permitted **only** in system-wide Admin reporting utilities, and must be guarded with explicit Admin authorization:

```php
// ✅ SAFE: Only permitted in system Admin context after explicit role verification
if (! auth()->user()?->hasRole(RolesEnum::ADMIN)) {
    abort(403, 'Acesso não autorizado.');
}
```

---

### 5. Request Parameter Spoofing (`org_id` Manipulation)

**Vulnerability**: Accepting `org_id` in Form Requests or `request()->all()` allows an attacker to inject an `org_id` payload and assign records to another organization.

```php
// ❌ DANGEROUS: Mass assignment allows attacker to override org_id in payload!
$course = Course::create($request->all());
```

```php
// ✅ SAFE: Allow OrgScope creating hook to auto-assign org_id from authenticated context,
// or explicitly set org_id from authenticated session (never from request body).
$validated = $request->validated();
unset($validated['org_id']); // Ensure client input cannot override org_id

$course = Course::create($validated);
```

---

### 6. Authorization Policy Checks (Role vs Tenant Dual-Verification)

**Vulnerability**: A policy checking role permissions without checking tenant ownership allows a Gestor from Tenant A to modify Tenant B's data.

```php
// ❌ INSECURE POLICY: Checks role, but ignores tenant boundary!
public function update(User $user, Course $course): bool
{
    return $user->hasRole(RolesEnum::GESTOR); // Insecure! Gestor A can edit Gestor B's course.
}
```

```php
// ✅ SECURE POLICY: Checks role AND tenant ownership
public function update(User $user, Course $course): bool
{
    if ($user->hasRole(RolesEnum::ADMIN)) {
        return true;
    }

    return $user->hasRole(RolesEnum::GESTOR) && $course->org_id === $user->org_id;
}
```

---

### 7. Aluno Direct Scoped Model Access Vulnerability

**Vulnerability**: Alunos have `user->org_id = null`. If an Aluno executes `Course::all()`, `OrgScope` applies `whereRaw('1 = 0')` as a safety fallback. If a developer attempts to bypass this by removing `OrgScope`, the Aluno sees all courses from all tenants!

```php
// ❌ DANGEROUS: Bypassing global scope for Alunos leaks all tenant courses!
$courses = Course::withoutGlobalScopes()->get();
```

```php
// ✅ SAFE: Aluno access MUST always scope through course_user enrollment pivot
$user = auth()->user();
$enrolledCourses = $user->courses() // BelongsToMany through course_user
    ->where('is_published', true)
    ->get();
```

---

### 8. Polymorphic / Loose Foreign Key Validation

**Vulnerability**: Tables like `course_completion_rules.target_id` or `forum_reports.postable_id` carry target IDs without database foreign key constraints. An attacker could submit a `target_id` belonging to a different tenant.

```php
// ❌ INSECURE: Accepts target_id without tenant validation
$rule = CourseCompletionRule::create([
    'course_id' => $course->id,
    'target_id' => $request->input('target_id'), // Could be a lesson from another tenant!
]);
```

```php
// ✅ SECURE: Validate that referenced target_id belongs to the current tenant
$targetLesson = Lesson::whereHas('module.course', function ($q) use ($userOrgId) {
    $q->where('org_id', $userOrgId);
})->findOrFail($request->input('target_id'));

$rule = CourseCompletionRule::create([
    'course_id' => $course->id,
    'target_id' => $targetLesson->id,
]);
```

---

## 🚩 Security Red Flags & Rationalization Table

| Rationalization / Excuse                                                   | Security Reality                                                                                                           |
| -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| _"Using `DB::table` is faster for simple queries."_                        | **REJECTED**: `DB::table` bypasses `OrgScope` entirely. You must manually add `->where('org_id', $orgId)` or use Eloquent. |
| _"The record ID was generated by the backend, so guessing is impossible."_ | **REJECTED**: Sequential integer auto-increment IDs (`id = 1, 2, 3`) are easily guessable. Always enforce tenant checks.   |
| _"The user is a Gestor, so they can see all users."_                       | **REJECTED**: Gestors are strictly isolated to their own `org_id`. `User` queries in Gestor views MUST filter by `org_id`. |
| _"I removed `OrgScope` because Alunos have `org_id = null`."_              | **REJECTED**: Never remove `OrgScope` for Alunos. Query enrolled courses through `$user->courses()` instead.               |
| _"Admins will be the only ones using this route."_                         | **REJECTED**: Unless protected by `role:admin` middleware, Gestors or Alunos could hit the endpoint.                       |

---

## 🔍 Code Review Security Checklist

When reviewing code for tenant security, verify every item on this checklist:

- [ ] **Directly Scoped Models**: Does every org-scoped model use the `OrgScope` trait?
- [ ] **Cascade-Inherited Models**: Are `Module`, `Lesson`, `Quiz`, etc., scoped via parent relations (`whereHas('module.course')`) or Policy checks?
- [ ] **Query Builder Audit**: Are any `DB::table()` queries touching org-scoped tables missing explicit `org_id` filtering?
- [ ] **User Queries**: Are all `User` model queries in Gestor controllers filtered by `where('org_id', $gestorOrgId)`?
- [ ] **Global Scope Audit**: Is `withoutGlobalScope(OrgScope::class)` absent from non-Admin application flows?
- [ ] **Mass Assignment**: Is `org_id` omitted from request validation input or stripped before mass assignment?
- [ ] **Authorization Policies**: Do all Policy methods verify `$record->org_id === $user->org_id` for Gestors?
- [ ] **Aluno Access**: Are Aluno queries routed exclusively through active enrollment relationships (`$user->courses()`)?
- [ ] **Loose FKs**: Are loose foreign keys (`target_id`, `postable_id`) explicitly validated against tenant ownership?
