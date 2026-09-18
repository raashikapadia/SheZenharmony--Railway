<?php

use App\Http\Controllers\Api\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Api\Admin\QuestionnaireController as AdminQuestionnaireController;
use App\Http\Controllers\Api\Admin\ScoreBandController as AdminScoreBandController;
use App\Http\Controllers\Api\AssessmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\StudentPasswordResetController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\DiaryController;
use App\Http\Controllers\Api\HelplineResourceController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\GratitudeEntryController;
use App\Http\Controllers\Api\PersonalGuidanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\WellbeingActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:3,10');
    Route::post('/auth/forgot-password', [StudentPasswordResetController::class, 'requestCode'])->middleware('throttle:3,10');
    Route::post('/auth/verify-reset-code', [StudentPasswordResetController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::post('/auth/reset-password', [StudentPasswordResetController::class, 'reset'])->middleware('throttle:10,1');
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questionnaires/active', [QuestionnaireController::class, 'active']);
    Route::get('/interventions', [InterventionController::class, 'index']);
    Route::get('/wellbeing-activities', [WellbeingActivityController::class, 'index']);
    // Admin-published helplines for the student Resource tab.
    Route::get('/helplines', [HelplineResourceController::class, 'index']);
    // Games and quizzes are part of Positive Engagement.
    Route::get('/positive-engagement/quizzes', [QuizController::class, 'index']);
    Route::get('/positive-engagement/quizzes/{quiz}', [QuizController::class, 'show']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::delete('/auth/account', [AuthController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'student.active'])->group(function (): void {
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/consent', [AuthController::class, 'consent']);
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/positive-engagement/quizzes/{quiz}/complete', [QuizController::class, 'complete']);
        Route::get('/positive-engagement/gratitude', [GratitudeEntryController::class, 'index']);
        Route::post('/positive-engagement/gratitude', [GratitudeEntryController::class, 'store']);
        Route::delete('/positive-engagement/gratitude/{gratitudeEntry}', [GratitudeEntryController::class, 'destroy']);

        // The student's own diary. There is deliberately no admin equivalent
        // of these routes: the writing is encrypted at rest and reachable only
        // by the student who wrote it.
        Route::get('/diary', [DiaryController::class, 'index']);
        Route::post('/diary/sync', [DiaryController::class, 'sync']);

        Route::get('/personal-guidance/for-you', [PersonalGuidanceController::class, 'forYou']);
        Route::get('/personal-guidance/current', [PersonalGuidanceController::class, 'current']);
        Route::get('/personal-guidance/another', [PersonalGuidanceController::class, 'another']);
        Route::get('/personal-guidance/favourites', [PersonalGuidanceController::class, 'favourites']);
        Route::post('/personal-guidance/{guidance}/favourite', [PersonalGuidanceController::class, 'favourite']);
        Route::delete('/personal-guidance/{guidance}/favourite', [PersonalGuidanceController::class, 'unfavourite']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function (): void {
        Route::get('/questionnaires', [AdminQuestionnaireController::class, 'index']);
        Route::post('/questionnaires', [AdminQuestionnaireController::class, 'store']);
        Route::get('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'show']);
        Route::put('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'destroy']);
        Route::patch('/questionnaires/{questionnaire}/activate', [AdminQuestionnaireController::class, 'activate']);
        Route::patch('/questionnaires/{questionnaire}/deactivate', [AdminQuestionnaireController::class, 'deactivate']);
        Route::post('/questionnaires/{questionnaire}/new-version', [AdminQuestionnaireController::class, 'createNewVersion']);

        Route::patch('/questionnaires/{questionnaire}/questions/reorder', [AdminQuestionController::class, 'reorder']);
        Route::post('/questionnaires/{questionnaire}/questions', [AdminQuestionController::class, 'store']);
        Route::put('/questionnaires/{questionnaire}/questions/{question}', [AdminQuestionController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}/questions/{question}', [AdminQuestionController::class, 'destroy']);

        Route::post('/questionnaires/{questionnaire}/score-bands', [AdminScoreBandController::class, 'store']);
        Route::put('/questionnaires/{questionnaire}/score-bands/{band}', [AdminScoreBandController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}/score-bands/{band}', [AdminScoreBandController::class, 'destroy']);
    });
});
