<?php

use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\EssayGradingController;
use App\Http\Controllers\ImpersonateOrgController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\QuizQuestionController;
use App\Http\Controllers\StudentCourseController;
use App\Http\Controllers\StudentQuizController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPEC-04 §2 / RF23 & UC18 — Organization CRUD + Impersonate Org, both
// reserved to `role:admin` (see `auth-orgs-conventions` skill).
Route::middleware(['auth', 'role:admin'])->group(function (): void {
    Route::resource('organizations', OrganizationController::class)->except(['show']);

    Route::post('organizations/{organization}/impersonate', [ImpersonateOrgController::class, 'store'])
        ->name('impersonate-org.store');
    Route::delete('impersonate-org', [ImpersonateOrgController::class, 'destroy'])
        ->name('impersonate-org.destroy');
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

// SPEC-04 RF01/RF02 — Authentication + Password Reset (see the
// `auth-orgs-architecture` skill).
require __DIR__.'/auth.php';
