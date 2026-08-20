<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuItemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_only_sees_available_items(): void
    {
        MenuItem::factory()->create(['is_available' => true]);
        MenuItem::factory()->create(['is_available' => false]);

        $response = $this->getJson('/api/menu-items');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_public_can_filter_by_category(): void
    {
        $bowls = Category::factory()->create(['slug' => 'bowls']);
        $drinks = Category::factory()->create(['slug' => 'drinks']);

        MenuItem::factory()->create(['category_id' => $bowls->id]);
        MenuItem::factory()->create(['category_id' => $drinks->id]);

        $response = $this->getJson('/api/menu-items?category=bowls');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_public_can_filter_featured_items(): void
    {
        MenuItem::factory()->create(['is_featured' => true]);
        MenuItem::factory()->create(['is_featured' => false]);

        $response = $this->getJson('/api/menu-items?featured=1');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_guest_cannot_create_menu_item(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/menu-items', [
            'category_id' => $category->id,
            'name' => ['en' => 'Test Item', 'es' => 'Artículo de Prueba'],
            'description' => ['en' => 'A test item', 'es' => 'Un artículo de prueba'],
            'price' => 9.99,
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_menu_item(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->post('/api/admin/menu-items', [
            'category_id' => $category->id,
            'name' => ['en' => 'Caesar Salad', 'es' => 'Ensalada César'],
            'description' => [
                'en' => 'Romaine, parmesan, croutons, house dressing',
                'es' => 'Romana, parmesano, crutones, aderezo de la casa',
            ],
            'price' => 12.50,
            'image' => UploadedFile::fake()->create('caesar.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('name.en', 'Caesar Salad');
        $this->assertDatabaseHas('menu_items', ['slug' => 'caesar-salad']);
    }

    public function test_menu_item_requires_valid_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/menu-items', [
            'category_id' => 999,
            'name' => ['en' => 'Test Item', 'es' => 'Artículo de Prueba'],
            'description' => ['en' => 'Test', 'es' => 'Prueba'],
            'price' => 9.99,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_admin_can_delete_menu_item(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = MenuItem::factory()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/menu-items/{$item->id}")->assertOk();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }
}