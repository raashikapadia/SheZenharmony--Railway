<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\InterventionController;
use App\Http\Controllers\Api\QuestionController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::prefix('v1')->group(function (): void {
    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/interventions', [InterventionController::class, 'index']);
});
