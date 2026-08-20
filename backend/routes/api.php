<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\QuestionnaireController;
use App\Http\Controllers\Api\AssessmentController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questionnaires/active', [QuestionnaireController::class, 'active']);
    Route::get('/interventions', [InterventionController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/assessments', [AssessmentController::class, 'store']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });
});
