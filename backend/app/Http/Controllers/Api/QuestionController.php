<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StressQuestion;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function index(): JsonResponse
    {
        $questions = StressQuestion::query()
            ->with(['options' => fn ($query) => $query->orderBy('position')])
            ->where('is_active', true)
            ->orderBy('position')
            ->get();

        return response()->json([
            'data' => $questions,
            'notice' => 'Development/demo questions only until the client supplies the approved assessment framework.',
        ]);
    }
}
