<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactMessageApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_submit_contact_form(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'What are your opening hours?',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['email' => 'jane@example.com']);
    }

    public function test_contact_form_requires_valid_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Jane Doe',
            'email' => 'not-an-email',
            'message' => 'Test message',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_guest_cannot_view_contact_messages(): void
    {
        $response = $this->getJson('/api/admin/contact-messages');

        $response->assertUnauthorized();
    }

    public function test_admin_can_view_and_it_marks_message_as_read(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $message = ContactMessage::factory()->create(['is_read' => false]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/contact-messages/{$message->id}")->assertOk();

        $this->assertTrue($message->fresh()->is_read);
    }
}