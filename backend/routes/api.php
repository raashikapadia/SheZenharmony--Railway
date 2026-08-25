<?php

use App\Http\Controllers\Api\Admin\QuestionController as AdminQuestionController;
use App\Http\Controllers\Api\Admin\QuestionnaireController as AdminQuestionnaireController;
use App\Http\Controllers\Api\Admin\ScoreBandController as AdminScoreBandController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\AssessmentController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questionnaires/active', [QuestionnaireController::class, 'active']);
    Route::get('/interventions', [InterventionController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/assessments', [AssessmentController::class, 'index']);
        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('api.admin.')->group(function (): void {
        Route::get('/questionnaires', [AdminQuestionnaireController::class, 'index']);
        Route::post('/questionnaires', [AdminQuestionnaireController::class, 'store']);
        Route::get('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'show']);
        Route::put('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}', [AdminQuestionnaireController::class, 'destroy']);
        Route::patch('/questionnaires/{questionnaire}/activate', [AdminQuestionnaireController::class, 'activate']);
        Route::patch('/questionnaires/{questionnaire}/deactivate', [AdminQuestionnaireController::class, 'deactivate']);

        Route::patch('/questionnaires/{questionnaire}/questions/reorder', [AdminQuestionController::class, 'reorder']);
        Route::post('/questionnaires/{questionnaire}/questions', [AdminQuestionController::class, 'store']);
        Route::put('/questionnaires/{questionnaire}/questions/{question}', [AdminQuestionController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}/questions/{question}', [AdminQuestionController::class, 'destroy']);

        Route::post('/questionnaires/{questionnaire}/score-bands', [AdminScoreBandController::class, 'store']);
        Route::put('/questionnaires/{questionnaire}/score-bands/{band}', [AdminScoreBandController::class, 'update']);
        Route::delete('/questionnaires/{questionnaire}/score-bands/{band}', [AdminScoreBandController::class, 'destroy']);
    });
});
