<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_not_admins_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_non_admin_token_cannot_use_admin_routes(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $message = ContactMessage::create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'message' => 'Hello there',
            'is_read' => false,
        ]);

        $this->getJson('/api/admin/contact-messages')->assertForbidden();
        $this->deleteJson("/api/admin/contact-messages/{$message->id}")->assertForbidden();
        $this->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_non_admin_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'staff@ensalada.com',
            'password' => Hash::make('secret-pass'),
            'role' => 'user',
        ]);

        $this->postJson('/api/login', [
            'email' => 'staff@ensalada.com',
            'password' => 'secret-pass',
        ])->assertUnprocessable();
    }

    public function test_new_contact_messages_start_unread_and_keep_their_branch(): void
    {
        $branch = Branch::create([
            'name' => ['en' => 'Beirut', 'es' => 'Beirut'],
            'slug' => 'beirut',
            'is_active' => true,
        ]);

        $this->postJson('/api/contact', [
            'branch' => 'beirut',
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'message' => 'What are your opening hours?',
        ])->assertCreated();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'jane@example.com',
            'branch_id' => $branch->id,
            'is_read' => false,
        ]);
    }

    public function test_inactive_branches_are_hidden_from_the_public(): void
    {
        $branch = Branch::create([
            'name' => ['en' => 'Closed', 'es' => 'Closed'],
            'slug' => 'closed',
            'is_active' => false,
        ]);

        $this->getJson("/api/branches/{$branch->id}")->assertNotFound();
        $this->getJson('/api/menu-items?branch=closed')->assertNotFound();
        $this->getJson('/api/site-settings?branch=closed')->assertNotFound();
        $this->getJson('/api/branches?all=1')->assertJsonMissing(['slug' => 'closed']);
    }

    public function test_admin_can_still_list_inactive_branches(): void
    {
        Branch::create([
            'name' => ['en' => 'Closed', 'es' => 'Closed'],
            'slug' => 'closed',
            'is_active' => false,
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/branches?all=1')->assertOk()->assertJsonFragment(['slug' => 'closed']);
    }

    public function test_tag_show_route_no_longer_exists(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $tag = Tag::create(['name' => 'Vegan']);

        // Was a 500 (missing controller method); now a plain 405.
        $this->getJson("/api/admin/tags/{$tag->id}")->assertStatus(405);
    }

    public function test_saving_a_site_setting_clears_the_public_cache(): void
    {
        $branch = Branch::create([
            'name' => ['en' => 'Beirut', 'es' => 'Beirut'],
            'slug' => 'beirut',
            'is_active' => true,
        ]);

        SiteSetting::set('hero_title', 'Old title', $branch->id);
        $this->getJson('/api/site-settings?branch=beirut')->assertJsonFragment(['hero_title' => 'Old title']);

        // Same call the Filament settings page makes when you press Save.
        SiteSetting::set('hero_title', 'New title', $branch->id);

        $this->getJson('/api/site-settings?branch=beirut')->assertJsonFragment(['hero_title' => 'New title']);
    }
}
