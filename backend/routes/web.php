<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminInterventionController;
use App\Http\Controllers\Web\AdminQuestionController;
use App\Http\Controllers\Web\AdminQuestionnaireController;
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
    Route::resource('questions', AdminQuestionController::class)->except(['show']);
    Route::resource('questionnaires', AdminQuestionnaireController::class)->except(['show']);
    Route::resource('interventions', AdminInterventionController::class)->except(['show']);
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
});
