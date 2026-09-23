<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChatBuddyMessageRequest;
use App\Services\ShezenChatService;
use Illuminate\Http\JsonResponse;
class ChatBuddyController extends Controller
{
    public function index(ShezenChatService $service): JsonResponse { return response()->json($service->publishedPayload()); }
    public function message(ChatBuddyMessageRequest $request, ShezenChatService $service): JsonResponse { return response()->json($service->replyForPublished($request->string('message')->toString())); }
}
