<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;

class ContactMessageController extends Controller
{
    /**
     * POST /api/contact
     * Public: submission from the website's contact form.
     * Rate limited to 10/minute per IP (see routes/api.php).
     */
    public function store(StoreContactMessageRequest $request)
    {
        $contactMessage = ContactMessage::create($request->validated());

        // Optional: notify the admin by email here, e.g.
        // Mail::to(config('mail.admin_address'))->send(new NewContactMessage($contactMessage));

        return response()->json(['message' => 'Thanks, we will get back to you soon!'], 201);
    }

    /**
     * GET /api/admin/contact-messages
     * Admin only: inbox, unread first.
     */
    public function index()
    {
        $messages = ContactMessage::orderBy('is_read')->orderByDesc('created_at')->get();

        return response()->json($messages);
    }

    /**
     * GET /api/admin/contact-messages/{contactMessage}
     * Viewing a message also marks it read.
     */
    public function show(ContactMessage $contactMessage)
    {
        if (! $contactMessage->is_read) {
            $contactMessage->update(['is_read' => true]);
        }

        return response()->json($contactMessage);
    }

    /**
     * DELETE /api/admin/contact-messages/{contactMessage}
     */
    public function destroy(ContactMessage $contactMessage)
    {
        $contactMessage->delete();

        return response()->json(['message' => 'Message deleted']);
    }
}