<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public chatbot, no auth required
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:300'],
            'branch' => ['nullable', 'string', 'max:100'],
            // how many fallback ("I didn't get that") replies in a row the
            // visitor has already had — lets the bot escalate to a human
            'fallback_streak' => ['nullable', 'integer', 'min:0', 'max:10'],
        ];
    }
}
