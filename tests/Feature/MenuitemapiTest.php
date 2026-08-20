<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\menu_item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_only_sees_available_items(): void
    {
        menu_item::factory()->create(['is_available' => true]);
        menu_item::factory()->create(['is_available' => false]);

        $response = $this->getJson('/api/menu-items');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_public_can_filter_by_category(): void
    {
        $bowls = Category::factory()->create(['slug' => 'bowls']);
        $drinks = Category::factory()->create(['slug' => 'drinks']);

        menu_item::factory()->create(['category_id' => $bowls->id]);
        menu_item::factory()->create(['category_id' => $drinks->id]);

        $response = $this->getJson('/api/menu-items?category=bowls');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_public_can_filter_featured_items(): void
    {
        menu_item::factory()->create(['is_featured' => true]);
        menu_item::factory()->create(['is_featured' => false]);

        $response = $this->getJson('/api/menu-items?featured=1');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_guest_cannot_create_menu_item(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/menu-items', [
            'category_id' => $category->id,
            'name' => 'Test Item',
            'description' => 'A test item',
            'price' => 9.99,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_menu_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/menu-items', [
            'category_id' => $category->id,
            'name' => 'Caesar Salad',
            'description' => 'Romaine, parmesan, croutons, house dressing',
            'price' => 12.50,
        ]);

        $response->assertCreated()->assertJsonFragment(['name' => 'Caesar Salad']);
        $this->assertDatabaseHas('menu_items', ['slug' => 'caesar-salad']);
    }

    public function test_menu_item_requires_valid_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/menu-items', [
            'category_id' => 999, // doesn't exist
            'name' => 'Test Item',
            'description' => 'Test',
            'price' => 9.99,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_admin_can_delete_menu_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = menu_item::factory()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/menu-items/{$item->id}")->assertOk();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }
}