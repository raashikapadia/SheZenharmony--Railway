<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminInterventionController;
use App\Http\Controllers\Web\AdminPersonalGuidanceController;
use App\Http\Controllers\Web\AdminQuestionController;
use App\Http\Controllers\Web\AdminQuestionnaireController;
use App\Http\Controllers\Web\AdminStudentController;
use App\Http\Controllers\Web\AdminStudentStressController;
use App\Http\Controllers\Web\AdminWellbeingActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/students', [AdminStudentController::class, 'index'])->name('students.index');
    Route::get('/students/{student}', [AdminStudentController::class, 'show'])->name('students.show');
    Route::get('/students/{student}/edit', [AdminStudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [AdminStudentController::class, 'update'])->name('students.update');
    Route::get('/student-stress', [AdminStudentStressController::class, 'index'])->name('student-stress.index');
    Route::get('/student-stress/{student}', [AdminStudentStressController::class, 'show'])->name('student-stress.show');
    Route::resource('questions', AdminQuestionController::class)->except(['show']);
    Route::resource('questionnaires', AdminQuestionnaireController::class)->except(['show']);
    Route::resource('interventions', AdminInterventionController::class)->except(['show']);
    Route::resource('wellbeing_activities', AdminWellbeingActivityController::class)->except(['show']);
    Route::resource('personal-guidance', AdminPersonalGuidanceController::class)->except(['show'])->parameters(['personal-guidance' => 'personalGuidance']);
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
});
