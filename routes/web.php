<?php

use App\Http\Controllers\ImpersonateOrgController;
use App\Http\Controllers\OrganizationController;
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

// SPEC-04 RF01/RF02 — Authentication + Password Reset (see the
// `auth-orgs-architecture` skill).
require __DIR__.'/auth.php';
