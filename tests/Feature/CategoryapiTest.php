<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_active_categories(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()->assertJsonCount(1);
    }

    public function test_guest_cannot_create_category(): void
    {
        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Test Category',
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Signature Bowls',
            'description' => 'Our house specials',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Signature Bowls', 'slug' => 'signature-bowls']);

        $this->assertDatabaseHas('categories', ['slug' => 'signature-bowls']);
    }

    public function test_category_creation_requires_a_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/categories', []);

        $response->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_admin_can_reorder_categories(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $first = Category::factory()->create(['sort_order' => 0]);
        $second = Category::factory()->create(['sort_order' => 1]);

        $this->postJson('/api/admin/categories/reorder', [
            'order' => [
                ['id' => $second->id, 'sort_order' => 0],
                ['id' => $first->id, 'sort_order' => 1],
            ],
        ])->assertOk();

        $this->assertEquals(0, $second->fresh()->sort_order);
        $this->assertEquals(1, $first->fresh()->sort_order);
    }
}