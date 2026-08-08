<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EssayGradingController;
use App\Http\Controllers\ForumModerationController;
use App\Http\Controllers\ForumReplyController;
use App\Http\Controllers\ForumReportController;
use App\Http\Controllers\ForumTopicController;
use App\Http\Controllers\ImpersonateOrgController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
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

// SPEC-11 / RF11 — public, unauthenticated Landing Page. Replaces the
// Laravel default `welcome` stub; kept outside any `auth` middleware and
// registered before `auth.php`'s routes.
Route::get('/', [LandingPageController::class, 'show'])->name('landing.show');

// SPEC-04 §2 / RF23 & UC18 — Organization CRUD + Impersonate Org, both
// reserved to `role:admin` (see `auth-orgs-conventions` skill).
Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::resource('organizations', OrganizationController::class)->except(['show']);

    Route::post('organizations/{organization}/impersonate', [ImpersonateOrgController::class, 'store'])
        ->name('impersonate-org.store');
    Route::delete('impersonate-org', [ImpersonateOrgController::class, 'destroy'])
        ->name('impersonate-org.destroy');

    // SPEC-15 §5/RF33 — Admin-side audit trail UI. See the `role:gestor`
    // block below for the Gestor-side counterpart pointing at the same
    // controller methods (see `audit-logs-conventions` for why these are
    // two distinct route names/prefixes rather than one shared
    // `role:admin|gestor` group).
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('admin/audit-logs/export', [AuditLogController::class, 'export'])->name('admin.audit-logs.export');
});

// SPEC-15 §5/RF33 — Gestor-side audit trail UI, same controller as the
// Admin block above. `AuditLog`'s `OrgScope` global scope restricts a
// Gestor's query to their own `org_id` automatically.
Route::middleware(['auth', 'role:gestor'])->group(function (): void {
    Route::get('gestor/audit-logs', [AuditLogController::class, 'index'])->name('gestor.audit-logs.index');
    Route::get('gestor/audit-logs/export', [AuditLogController::class, 'export'])->name('gestor.audit-logs.export');
});

// RF04/RF05 — Aluno/Gestor CRUD + chunked CSV import, restricted to
// Admin/Gestor (see the `auth-orgs-maintenance` skill).
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('users/import', [UserImportController::class, 'create'])->name('users.import.create');
    Route::post('users/import/chunk', [UserImportController::class, 'chunk'])->name('users.import.chunk');

    Route::resource('users', UserController::class)->except(['show']);
});

// SPEC-05 §1 / RF06 & RF07 — Course/Module/Lesson CRUD + AJAX reorder,
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

// SPEC-08 RF08 — Quiz (1:1 with a Lesson) + nested QuizQuestion/QuizOption
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

    // SPEC-08 §2.1 — the Gestor's pending manual-grading queue + grade
    // action, gated by `QuizAttemptPolicy` rather than a Course/Module
    // /Lesson route parameter.
    Route::get('quiz-attempts/pending', [EssayGradingController::class, 'pending'])->name('quiz-attempts.pending');
    Route::get('quiz-attempts/{quizAttempt}', [EssayGradingController::class, 'show'])->name('quiz-attempts.show');
    Route::post('quiz-attempts/{quizAttempt}/grade', [EssayGradingController::class, 'grade'])->name('quiz-attempts.grade');
});

// SPEC-09 §1.2 / RF25 — Gestor/Admin per-course certificate list +
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
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->name('certificates.download');
});

// SPEC-06 RF03 & RF21 — Invitation Link management + manual enrollment
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
    Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store'])
        ->name('courses.enrollments.store');
    Route::delete('courses/{course}/enrollments/{user}', [EnrollmentController::class, 'destroy'])
        ->name('courses.enrollments.destroy');
});

// SPEC-06 RF03/RN09 — public, unauthenticated Smart Invitation flow: a
// student joins the platform (or authenticates into an already-existing
// account, per the multi-org adaptive flow) purely from a
// `/convite/{token}` link, with no prior session (see the
// `invitations-architecture` skill).
Route::middleware('guest')->group(function (): void {
    Route::get('convite/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('convite/check-email', [InvitationController::class, 'checkEmail'])->name('invitation.check-email');
    Route::post('convite/{token}', [InvitationController::class, 'store'])->name('invitation.store');
});

// SPEC-07 RF19 — "Meus Cursos", the Aluno's own enrollments across every
// Organization they belong to. `role:aluno` rather than
// `student.enrolled` — this listing IS the enrollment data, with no
// single `{course}`/`{lesson}` route parameter to gate (see
// `StudentCourseController`).
Route::middleware(['auth', 'role:aluno'])->group(function (): void {
    Route::get('meus-cursos', [StudentCourseController::class, 'index'])->name('student.courses.index');
});

// SPEC-07 RF20 — the student-facing classroom/lesson/progress routes,
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

    // SPEC-08 RF09 — the Aluno's quiz-taking flow, nested under `{lesson}`
    // (never a bare `{quiz}`) so `EnsureStudentIsEnrolled::resolveCourse()`
    // keeps working unmodified (see the `quizzes-architecture` skill).
    // `submit` is a distinct `/quiz/submit` suffix — not the same
    // `POST lessons/{lesson}/quiz` URI as the Gestor's `quizzes.store`
    // above — Laravel's route collection keys routes by method+URI, so an
    // identical pair would silently overwrite one of the two named routes.
    Route::get('lessons/{lesson}/quiz', [StudentQuizController::class, 'show'])->name('student.quizzes.show');
    Route::post('lessons/{lesson}/quiz/submit', [StudentQuizController::class, 'submit'])->name('student.quizzes.submit');
});

// SPEC-10 §2/RF22/RF26 — the course discussion forum: Topic/Reply CRUD,
// `since_id` AJAX polling, and the "Denunciar" report action, all nested
// under `{course}` and gated by `student.enrolled` — mirrors the
// `classroom.*`/`student.quizzes.*` block above's middleware choice since
// forum access is enrollment-gated (RN10), not just role-gated (see the
// `EnsureStudentIsEnrolled` middleware). `{topic}`/`{reply}` are plain
// route parameters, not typed model bindings — see
// `ForumTopicController`'s docblock for why. `forum-replies.fetch` is the
// only route in this group carrying its own `throttle:60,1` (SPEC-10 §2),
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

// SPEC-10 §2/§2.2/RF26 — Gestor/Admin-only forum moderation: the direct
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

// SPEC-12 — Admin/Gestor dashboard, CSV export, and org-level system
// settings, restricted to `role:admin|gestor` (no dedicated Policy, see
// `dashboard-conventions`). The `admin.dashboard` route name is
// load-bearing: `components/layout/sidebar.blade.php` checks
// `Route::has('admin.dashboard')` and silently degrades to a dead `#`
// link if it is ever renamed.
Route::middleware(['auth', 'role:admin|gestor'])->group(function (): void {
    Route::get('admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');

    Route::get('admin/reports/{type}/export', [ReportExportController::class, 'stream'])
        ->whereIn('type', ['enrollments', 'certificates'])
        ->name('reports.export');

    Route::get('admin/settings', [SystemSettingController::class, 'edit'])->name('settings.edit');
    Route::put('admin/settings', [SystemSettingController::class, 'update'])->name('settings.update');
});

// SPEC-13 §Bucket 2 — the AJAX endpoints backing the topbar notification
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

// SPEC-09 §2 / RF17 — fully public, cross-tenant certificate validation.
// Deliberately OUTSIDE any `auth`/`guest`/`role` group — unlike
// `convite/*` below (which IS `guest`-gated, since an already
// -authenticated visitor is redirected away from it), this route must
// resolve identically for a fully anonymous visitor AND an already
// -logged-in Admin/Gestor/Aluno alike, so no middleware applies at all
// (see the `certificates-architecture` skill).
Route::get('validar-certificado/{hash}', [PublicCertificateController::class, 'show'])
    ->name('certificates.verify');

// SPEC-04 RF01/RF02 — Authentication + Password Reset (see the
// `auth-orgs-architecture` skill).
require __DIR__.'/auth.php';
