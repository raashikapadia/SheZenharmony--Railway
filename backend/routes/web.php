<?php

use App\Http\Controllers\Web\AdminAuthController;
use App\Http\Controllers\Web\AdminChatBuddyController;
use App\Http\Controllers\Web\AdminDashboardController;
use App\Http\Controllers\Web\AdminHelplineResourceController;
use App\Http\Controllers\Web\AdminInterventionController;
use App\Http\Controllers\Web\AdminPersonalGuidanceController;
use App\Http\Controllers\Web\AdminPositiveEngagementController;
use App\Http\Controllers\Web\AdminQuizController;
use App\Http\Controllers\Web\AdminQuestionController;
use App\Http\Controllers\Web\AdminQuestionnaireController;
use App\Http\Controllers\Web\AdminSectionController;
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
    Route::patch('/students/{student}/hold', [AdminStudentController::class, 'hold'])->name('students.hold');
    Route::patch('/students/{student}/reactivate', [AdminStudentController::class, 'reactivate'])->name('students.reactivate');
    Route::get('/student-stress/overview', [AdminStudentStressController::class, 'overview'])->name('student-stress.overview');
    Route::get('/student-stress', [AdminStudentStressController::class, 'index'])->name('student-stress.index');
    Route::get('/student-stress/analytics', [AdminStudentStressController::class, 'analytics'])->name('student-stress.analytics');
    Route::get('/student-stress/assessment/{assessment}', [AdminStudentStressController::class, 'assessment'])->name('student-stress.assessment');
    Route::get('/student-stress/{student}', [AdminStudentStressController::class, 'show'])->name('student-stress.show');
    Route::resource('questions', AdminQuestionController::class)->except(['show']);
    Route::get('questionnaires/trash', [AdminQuestionnaireController::class, 'trash'])->name('questionnaires.trash');
    Route::get('questionnaires/builder', [AdminQuestionnaireController::class, 'builder'])->name('questionnaires.builder');
    Route::get('questionnaires/results', [AdminQuestionnaireController::class, 'results'])->name('questionnaires.results');
    Route::get('questionnaires/analytics', [AdminQuestionnaireController::class, 'analytics'])->name('questionnaires.analytics');
    Route::get('questionnaires/versions', [AdminQuestionnaireController::class, 'versions'])->name('questionnaires.versions');
    Route::resource('questionnaires', AdminQuestionnaireController::class)->except(['show']);
    Route::patch('questionnaires/{questionnaire}/details', [AdminQuestionnaireController::class, 'updateDetails'])->name('questionnaires.details');
    Route::patch('questionnaires/{questionnaire}/ranges', [AdminQuestionnaireController::class, 'updateRanges'])->name('questionnaires.ranges');
    Route::post('questionnaires/{questionnaire}/new-version', [AdminQuestionnaireController::class, 'createVersion'])->name('questionnaires.new-version');
    Route::get('questionnaires/{questionnaire}/preview', [AdminQuestionnaireController::class, 'preview'])->name('questionnaires.preview');
    Route::patch('questionnaires/{questionnaire}/publish', [AdminQuestionnaireController::class, 'publish'])->name('questionnaires.publish');
    Route::patch('questionnaires/{questionnaire}/archive', [AdminQuestionnaireController::class, 'archive'])->name('questionnaires.archive');
    Route::patch('questionnaires/{questionnaire}/restore', [AdminQuestionnaireController::class, 'restore'])->name('questionnaires.restore');
    Route::delete('questionnaires/{questionnaire}/force', [AdminQuestionnaireController::class, 'forceDestroy'])->name('questionnaires.force-destroy');
    Route::get('questionnaires/{questionnaire}/scoring', [AdminQuestionnaireController::class, 'scoring'])->name('questionnaires.scoring');
    Route::resource('questionnaires.sections', AdminSectionController::class)->except(['show']);
    Route::prefix('questionnaires/{questionnaire}/sections/{section}/questions')
        ->name('questionnaires.sections.questions.')
        ->group(function (): void {
            Route::get('create', [AdminSectionController::class, 'createQuestion'])->name('create');
            Route::post('/', [AdminSectionController::class, 'storeQuestion'])->name('store');
            Route::get('{question}/edit', [AdminSectionController::class, 'editQuestion'])->name('edit');
            Route::put('{question}', [AdminSectionController::class, 'updateQuestion'])->name('update');
            Route::delete('{question}', [AdminSectionController::class, 'destroyQuestion'])->name('destroy');
        });
    Route::resource('interventions', AdminInterventionController::class)->except(['show']);
    Route::resource('wellbeing_activities', AdminWellbeingActivityController::class)->except(['show']);
    Route::resource('personal-guidance', AdminPersonalGuidanceController::class)->except(['show'])->parameters(['personal-guidance' => 'personalGuidance']);
    // Helpline resources shown in the student Resource tab.
    Route::resource('resources', AdminHelplineResourceController::class)->except(['show']);
    // Games and quizzes live under Positive Engagement. Declared before the
    // positive-engagement resource so the literal segment is not swallowed by
    // the resource's {intervention} parameter.
    Route::resource('positive-engagement/games-quizzes', AdminQuizController::class)
        ->except(['show'])
        ->parameters(['games-quizzes' => 'quiz'])
        ->names('positive-engagement.games-quizzes');
    Route::resource('positive-engagement', AdminPositiveEngagementController::class)
        ->except(['show'])
        ->parameters(['positive-engagement' => 'intervention']);
    // Shezen, the rule-based chat buddy. The student experience ships as
    // "Coming Soon"; the conversation content is managed here in the meantime.
    Route::resource('chatbuddy', AdminChatBuddyController::class)->except(['show']);
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');
});
