<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'message' => ['required', 'string', 'max:500'],
            // Prior turns of the conversation, so the bot has context.
            // Capped at 20 to bound both cost and prompt size.
            'history' => ['nullable', 'array', 'max:20'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ];
    }
}
