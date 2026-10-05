<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChatMessageRequest;
use App\Services\Chatbot\ChatbotEngine;
use App\Services\Chatbot\ChatContextBuilder;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatbotEngine $engine,
        private readonly ChatContextBuilder $contexts,
    ) {}

    /**
     * POST /api/chat
     * Body: { "message": "vegan options", "branch": "beirut", "fallback_streak": 0 }
     * Public, throttled (see routes/api.php). Returns the bot's reply:
     * { text, items[], actions[], chips[], is_fallback }
     */
    public function message(ChatMessageRequest $request)
    {
        $data = $request->validated();

        $reply = $this->engine->reply(
            $data['message'],
            $this->contexts->build($data['branch'] ?? null),
            (int) ($data['fallback_streak'] ?? 0),
        );

        return response()->json($reply);
    }

    /**
     * GET /api/chat/welcome?branch=beirut&mode=welcome|switched|chosen
     * The opening message (and quick-reply chips) for a fresh conversation,
     * or the note shown when the visitor changes branch mid-conversation.
     */
    public function welcome(Request $request)
    {
        $data = $request->validate([
            'branch' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:welcome,switched,chosen'],
        ]);

        return response()->json($this->engine->welcome(
            $this->contexts->build($data['branch'] ?? null),
            $data['mode'] ?? 'welcome',
        ));
    }
}
