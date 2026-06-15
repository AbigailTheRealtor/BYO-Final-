<?php

namespace App\Http\Controllers\AgentAi;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentAiChatController extends Controller
{
    public function ask(Request $request): JsonResponse
    {
        return response()->json(['status' => 'not_implemented']);
    }

    public function startSession(Request $request): JsonResponse
    {
        return response()->json(['status' => 'not_implemented']);
    }
}
