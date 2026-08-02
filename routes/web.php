<?php

use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ImpersonateOrgController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationLinkController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\StudentCourseController;
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
});

// SPEC-04 RF01/RF02 — Authentication + Password Reset (see the
// `auth-orgs-architecture` skill).
require __DIR__.'/auth.php';
