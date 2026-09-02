<?php

use App\Http\Controllers\Admin\UserAdminController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseCompletionRuleController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EssayGradingController;
use App\Http\Controllers\ForumModerationController;
use App\Http\Controllers\ForumReplyController;
use App\Http\Controllers\ForumReportController;
use App\Http\Controllers\ForumTopicController;
use App\Http\Controllers\GestorStudentController;
use App\Http\Controllers\ImpersonateOrgController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCertificateController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizQuestionController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\StudentCourseController;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use Illuminate\Support\Facades\Route;

// public, unauthenticated Landing Page. Replaces the
// Laravel default `welcome` stub; kept outside any `auth` middleware and
// registered before `auth.php`'s routes.
Route::get('/', [LandingPageController::class, 'show'])->name('landing.show');

// Organization CRUD + Impersonate Org, both
// reserved to `role:admin` (see `auth-orgs-conventions` skill).
Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::resource('organizations', OrganizationController::class)->except(['show']);

    Route::post('organizations/{organization}/impersonate', [ImpersonateOrgController::class, 'store'])
        ->name('impersonate-org.store');
    Route::delete('impersonate-org', [ImpersonateOrgController::class, 'destroy'])
        ->name('impersonate-org.destroy');

    // Admin-side audit trail UI. See the `role:gestor`
    // block below for the Gestor-side counterpart pointing at the same
    // controller methods (see `audit-logs-conventions` for why these are
    // two distinct route names/prefixes rather than one shared
    // `role:admin|gestor` group).
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('admin/audit-logs/export', [AuditLogController::class, 'export'])->name('admin.audit-logs.export');

    // cross-org, all-roles Admin user-management screen.
    // Registered here (not the `role:admin|gestor` group below that
    // serves the operational `users.*` resource) so "inacessível a
    // Gestores e Alunos" is enforced by middleware, not just the Policy.
    Route::get('admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
    Route::get('admin/users/{user}', [UserAdminController::class, 'show'])->name('admin.users.show');
    Route::get('admin/users/{user}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
    Route::put('admin/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
    Route::patch('admin/users/{user}/status', [UserAdminController::class, 'updateStatus'])->name('admin.users.status');
    Route::delete('admin/users/{user}', [UserAdminController::class, 'destroy'])->name('admin.users.destroy');

    // the operational single-org "Alunos & Gestores" screen,
    // Admin-exclusive (`role:admin`, mirroring the `users` navigation
    // item's `roles`): the Gestor's people management lives in the
    // dedicated `gestor.students.*` group below, never here. Tenant
    // resolution still goes through `ResolvesOrgContext` — an Admin
    // reaches this resource only while impersonating an Organization.
    Route::resource('users', UserController::class)->except(['show']);
});

// Gestor-side audit trail routes were removed :
// audit is a system-administration surface reserved to `role:admin`
// (`admin.audit-logs.*` in the group above), with parity enforced by the
// `audit-logs` navigation item's `roles` and by the OrgScope on `AuditLog`
// for the impersonation case.

// Aluno/Gestor chunked CSV import stays shared
// (`role:admin|gestor`): unlike the `users.*` CRUD above, it is an
// enrollment tool scoped to the acting user's own Organization's Courses
// (see `UserImportService::importChunk()`), i.e. part of managing their
// Organization's Alunos rather than part of the Admin-only users screen.
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('users/import', [UserImportController::class, 'create'])->name('users.import.create');
    Route::post('users/import/chunk', [UserImportController::class, 'chunk'])->name('users.import.chunk');
});

// the Gestor's exclusive Aluno directory: lists only the
// Alunos enrolled in the acting Gestor's own Organization's Courses, and
// lets them view/manage exactly those Alunos (edit profile/status and
// remove) — nothing beyond their own tenant and never another staff
// account (see `GestorStudentController` and `UserPolicy::*Student`).
// Distinct from the Admin-only `users.*` resource above by design (see
// `auth-orgs-conventions`): a separate controller, a separate route
// namespace and a `role:gestor`-only middleware group, so the boundary is
// enforced by middleware first and Policy second.
Route::middleware(['auth', 'role:gestor'])->group(function (): void {
    Route::get('gestor/students', [GestorStudentController::class, 'index'])->name('gestor.students.index');
    Route::get('gestor/students/{user}/edit', [GestorStudentController::class, 'edit'])->name('gestor.students.edit');
    Route::put('gestor/students/{user}', [GestorStudentController::class, 'update'])->name('gestor.students.update');
    Route::delete('gestor/students/{user}', [GestorStudentController::class, 'destroy'])->name('gestor.students.destroy');
});

// Course/Module/Lesson CRUD + AJAX reorder,
// restricted to Admin/Gestor (see the `courses-conventions` skill).
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::resource('courses', CourseController::class)->except(['show']);

    Route::post('courses/{course}/modules/reorder', [ModuleController::class, 'reorder'])
        ->name('modules.reorder');
    Route::resource('courses.modules', ModuleController::class)->shallow()->except(['show']);

    Route::post('modules/{module}/lessons/reorder', [LessonController::class, 'reorder'])
        ->name('lessons.reorder');
    Route::resource('modules.lessons', LessonController::class)->shallow()->except(['show']);
});

// Quiz (1:1 with a Lesson) + nested QuizQuestion/QuizOption
// CRUD + reorder, restricted to Admin/Gestor (see the
// `quizzes-conventions` skill). `quizzes.{create,store}` are reached via
// `{lesson}` (mirroring `modules.lessons`' shallow nesting one level
// further down); `{quiz}` alone resolves `edit`/`update`/`destroy`. There
// is no `quiz-questions/create|edit` full-page screen — per
// `quizzes/edit.blade.php`'s contract, Questions are authored via modals
// on the parent Quiz's single edit screen, so `quiz-questions` is routed
// explicitly (store/update/destroy/reorder only) rather than as a full
// `Route::resource()`.
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('lessons/{lesson}/quiz/create', [QuizController::class, 'create'])->name('quizzes.create');
    Route::post('lessons/{lesson}/quiz', [QuizController::class, 'store'])->name('quizzes.store');
    Route::get('quizzes/{quiz}/edit', [QuizController::class, 'edit'])->name('quizzes.edit');
    Route::put('quizzes/{quiz}', [QuizController::class, 'update'])->name('quizzes.update');
    Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->name('quizzes.destroy');

    Route::post('quizzes/{quiz}/quiz-questions', [QuizQuestionController::class, 'store'])
        ->name('quiz-questions.store');
    Route::post('quizzes/{quiz}/quiz-questions/reorder', [QuizQuestionController::class, 'reorder'])
        ->name('quiz-questions.reorder');
    Route::put('quiz-questions/{quiz_question}', [QuizQuestionController::class, 'update'])
        ->name('quiz-questions.update');
    Route::delete('quiz-questions/{quiz_question}', [QuizQuestionController::class, 'destroy'])
        ->name('quiz-questions.destroy');

    // the Gestor's pending manual-grading queue + grade
    // action, gated by `QuizAttemptPolicy` rather than a Course/Module
    // /Lesson route parameter.
    Route::get('quiz-attempts/pending', [EssayGradingController::class, 'pending'])->name('quiz-attempts.pending');
    Route::get('quiz-attempts/{quizAttempt}', [EssayGradingController::class, 'show'])->name('quiz-attempts.show');
    Route::post('quiz-attempts/{quizAttempt}/grade', [EssayGradingController::class, 'grade'])->name('quiz-attempts.grade');
});

// Gestor/Admin per-course certificate list +
// revocation + PDF download, restricted to Admin/Gestor (see the
// `certificates-conventions` skill). Not a `Route::resource()` —
// `certificates` has no `create`/`store`/`edit`/`update` staff-facing
// screens (issuance is fully automatic via `IssueCertificateAction`), so
// only `index`/`revoke`/`download` are routed explicitly.
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('courses/{course}/certificates', [CertificateController::class, 'index'])
        ->name('courses.certificates.index');
    Route::put('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])
        ->name('certificates.revoke');

    //  the Gestor/Admin's Course-level completion-rule CRUD
    // (`index`/`store`/`destroy` only, see `CourseCompletionRuleController`'s
    // docblock). Mirrors `courses.enrollments.*`'s nesting-under-`{course}`
    // pattern one block above.
    Route::get('courses/{course}/completion-rules', [CourseCompletionRuleController::class, 'index'])
        ->name('courses.completion-rules.index');
    Route::post('courses/{course}/completion-rules', [CourseCompletionRuleController::class, 'store'])
        ->name('courses.completion-rules.store');
    Route::delete('courses/{course}/completion-rules/{completion_rule}', [CourseCompletionRuleController::class, 'destroy'])
        ->name('courses.completion-rules.destroy');
});

//  `certificates.download` sits outside the `role:admin|gestor`
// group above: unlike `index`/`revoke`, download is also reachable by the
// Aluno who OWNS the certificate (their "Certificado ainda não disponível"
// classroom card turns into a download link once issued). Plain `auth`
// here; `CertificateController::download()`'s internal check still
// enforces staff-role-or-owner, so a non-owner Aluno remains blocked.
Route::middleware('auth')->group(function (): void {
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');
});

// self-service profile management, available to any
// authenticated user regardless of role (no `role:` restriction, unlike
// most groups in this file). `password.update` is throttled like the
// existing `routes/auth.php` login/reset endpoints; it must not collide
// with `password.store`, which belongs to the public password-reset flow
// in `routes/auth.php`.
Route::middleware('auth')->group(function (): void {
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

// Invitation Link management + manual enrollment
// panels, restricted to Admin/Gestor (see the `invitations-conventions`
// skill).
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::resource('courses.invitation-links', InvitationLinkController::class)
        ->shallow()
        ->only(['index', 'create', 'store', 'destroy']);

    // Not a `Route::resource()` — `course_user` is a pivot with no
    // `Enrollment` Eloquent model to route-bind (see `courses-architecture`),
    // so `destroy` takes both `{course}` and `{user}` explicitly rather than
    // a `shallow()` single-segment `{enrollment}`.
    Route::get('courses/{course}/enrollments', [EnrollmentController::class, 'index'])
        ->name('courses.enrollments.index');
    Route::get('courses/{course}/enrollments/search', [EnrollmentController::class, 'search'])
        ->name('courses.enrollments.search');
    Route::get('courses/{course}/enrollments/create', [EnrollmentController::class, 'create'])
        ->name('courses.enrollments.create');
    Route::post('courses/{course}/enrollments/store-student', [EnrollmentController::class, 'storeStudent'])
        ->name('courses.enrollments.store-student');
    Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])
        ->name('courses.enrollments.store');
    Route::delete('courses/{course}/enrollments/{user}', [EnrollmentController::class, 'destroy'])
        ->name('courses.enrollments.destroy');
});

// public, unauthenticated Smart Invitation flow: a
// student joins the platform (or authenticates into an already-existing
// account, per the multi-org adaptive flow) purely from a
// `/convite/{token}` link, with no prior session (see the
// `invitations-architecture` skill).
Route::middleware('guest')->group(function (): void {
    Route::get('convite/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    // Both POST endpoints are throttled per IP: they are public,
    // unauthenticated and answer questions about personal data (whether an
    // e-mail has an account, whether a CPF is already registered), so the
    // rate limit is what keeps them from being usable as enumeration
    // oracles over LGPD-sensitive data.
    Route::post('convite/check-email', [InvitationController::class, 'checkEmail'])
        ->middleware('throttle:20,1')
        ->name('invitation.check-email');
    Route::post('convite/{token}', [InvitationController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitation.store');
});

// "Meus Cursos", the Aluno's own enrollments across every
// Organization they belong to. `role:aluno` rather than
// `student.enrolled` — this listing IS the enrollment data, with no
// single `{course}`/`{lesson}` route parameter to gate (see
// `StudentCourseController`).
Route::middleware(['auth', 'role:aluno'])->group(function (): void {
    Route::get('meus-cursos', [StudentCourseController::class, 'index'])->name('student.courses.index');
});

// the student-facing classroom/lesson/progress routes,
// gated by `student.enrolled` (registered in `bootstrap/app.php`) rather
// than `role:aluno`/`CoursePolicy`/`LessonPolicy`: distinct from the
// Admin/Gestor `modules.lessons` management block above, this middleware
// also allows Admin (unconditionally) and Gestor (same-org) to preview a
// Course's classroom (see the `EnsureStudentIsEnrolled` middleware).
Route::middleware(['auth', 'student.enrolled'])->group(function (): void {
    Route::get('courses/{course}/classroom', [ClassroomController::class, 'show'])->name('classroom.show');
    Route::get('lessons/{lesson}', [ClassroomController::class, 'showLesson'])->name('classroom.lesson');
    Route::post('lessons/{lesson}/complete', [LessonProgressController::class, 'complete'])->name('lessons.complete');
    Route::post('lessons/{lesson}/progress', [LessonProgressController::class, 'updateProgress'])->name('lessons.progress');

    // the Aluno's quiz-taking flow, nested under `{lesson}`
    // (never a bare `{quiz}`) so `EnsureStudentIsEnrolled::resolveCourse()`
    // keeps working unmodified (see the `quizzes-architecture` skill).
    // `submit` is a distinct `/quiz/submit` suffix — not the same
    // `POST lessons/{lesson}/quiz` URI as the Gestor's `quizzes.store`
    // above — Laravel's route collection keys routes by method+URI, so an
    // identical pair would silently overwrite one of the two named routes.
    Route::get('lessons/{lesson}/quiz', [StudentQuizController::class, 'show'])->name('student.quizzes.show');
    Route::post('lessons/{lesson}/quiz/submit', [StudentQuizController::class, 'submit'])->name('student.quizzes.submit');
});

// the course discussion forum: Topic/Reply CRUD,
// `since_id` AJAX polling, and the "Denunciar" report action, all nested
// under `{course}` and gated by `student.enrolled` — mirrors the
// `classroom.*`/`student.quizzes.*` block above's middleware choice since
// forum access is enrollment-gated , not just role-gated (see the
// `EnsureStudentIsEnrolled` middleware). `{topic}`/`{reply}` are plain
// route parameters, not typed model bindings — see
// `ForumTopicController`'s docblock for why. `forum-replies.fetch` is the
// only route in this group carrying its own `throttle:60,1`
// scoped to just the polling endpoint rather than the whole group so
// posting/editing/deleting are never throttled by it.
Route::middleware(['auth', 'student.enrolled'])->prefix('courses/{course}/forum')->group(function (): void {
    Route::get('/', [ForumTopicController::class, 'index'])->name('forum.index');
    Route::get('/create', [ForumTopicController::class, 'create'])->name('forum.create');
    Route::post('/', [ForumTopicController::class, 'store'])->name('forum.store');
    Route::get('/topics/{topic}/edit', [ForumTopicController::class, 'edit'])->name('forum.edit');
    Route::get('/topics/{topic}', [ForumTopicController::class, 'show'])->name('forum.show');
    Route::put('/topics/{topic}', [ForumTopicController::class, 'update'])->name('forum.update');
    Route::delete('/topics/{topic}', [ForumTopicController::class, 'destroy'])->name('forum.destroy');

    Route::post('/topics/{topic}/replies', [ForumReplyController::class, 'store'])->name('forum-replies.store');
    Route::put('/topics/{topic}/replies/{reply}', [ForumReplyController::class, 'update'])->name('forum-replies.update');
    Route::delete('/topics/{topic}/replies/{reply}', [ForumReplyController::class, 'destroy'])->name('forum-replies.destroy');
    Route::get('/topics/{topic}/replies/fetch', [ForumReplyController::class, 'fetchNew'])
        ->middleware('throttle:60,1')
        ->name('forum-replies.fetch');

    Route::post('/report', [ForumReportController::class, 'store'])->name('forum-reports.store');
});

// Gestor/Admin-only forum moderation: the direct
// pin toggle (independent of any report) and the pending-report queue's
// dismiss/remove actions, restricted to `role:admin|gestor` rather than
// `student.enrolled` (mirrors `quiz-attempts.pending`'s same role-gated,
// Policy-scoped-within-the-controller convention above).
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::post('courses/{course}/forum/topics/{topic}/pin', [ForumTopicController::class, 'pin'])
        ->name('forum.pin');

    Route::get('forum/moderation', [ForumModerationController::class, 'index'])->name('forum-moderation.index');
    Route::post('forum/moderation/{forumReport}/dismiss', [ForumModerationController::class, 'dismiss'])
        ->name('forum-moderation.dismiss');
    Route::post('forum/moderation/{forumReport}/remove', [ForumModerationController::class, 'remove'])
        ->name('forum-moderation.remove');
});

// Admin/Gestor dashboard + CSV export, restricted to
// `role:admin|gestor` (no dedicated Policy, see `dashboard-conventions`).
// The `admin.dashboard` route name is load-bearing:
// `components/layout/sidebar.blade.php` checks `Route::has('admin.dashboard')`
// and silently degrades to a dead `#` link if it is ever renamed.
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('admin/reports/{type}/export', [ReportExportController::class, 'stream'])
        ->whereIn('type', ['enrollments', 'certificates'])
        ->name('reports.export');
});

// System settings (SMTP/logo/signature overrides) are a
// system-administration surface reserved to `role:admin` — the Gestor
// neither sees the menu item nor reaches the screen. An Admin without an
// active Impersonate Org writes the GLOBAL row; impersonating an
// Organization writes that org's override row
// (`SystemSettingController`'s resolution is unchanged). A dedicated
// `role:admin` group keeps the boundary enforced by middleware, not just
// hidden in the menu.
Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::get('admin/settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('admin/settings', [SystemSettingController::class, 'update'])->name('settings.update');
});

// the AJAX endpoints backing the topbar notification
// bell. `DatabaseNotification` has no Policy/OrgScope of its own, so
// `NotificationController` manually scopes every query to
// `$request->user()->notifications()` rather than relying on a route-model
// binding (see the `notifications-conventions` skill).
Route::middleware('auth')->group(function (): void {
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});

// fully public, cross-tenant certificate validation.
// Deliberately OUTSIDE any `auth`/`guest`/`role` group — unlike
// `convite/*` below (which IS `guest`-gated, since an already
// -authenticated visitor is redirected away from it), this route must
// resolve identically for a fully anonymous visitor AND an already
// -logged-in Admin/Gestor/Aluno alike, so no middleware applies at all
// (see the `certificates-architecture` skill).
Route::get('validar-certificado/{hash?}', [PublicCertificateController::class, 'show'])
    ->name('certificates.verify');

// Authentication + Password Reset routes.
require __DIR__.'/auth.php';
