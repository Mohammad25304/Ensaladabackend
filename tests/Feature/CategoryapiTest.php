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
            'name' => ['en' => 'Test Category', 'es' => 'Categoría de Prueba'],
        ]);

        $response->assertUnauthorized();
    }

    public function test_admin_can_create_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/categories', [
            'name' => ['en' => 'Signature Bowls', 'es' => 'Bowls de Autor'],
            'description' => ['en' => 'Our house specials', 'es' => 'Nuestras especialidades'],
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['slug' => 'signature-bowls'])
            ->assertJsonPath('name.en', 'Signature Bowls')
            ->assertJsonPath('name.es', 'Bowls de Autor');

        $this->assertDatabaseHas('categories', ['slug' => 'signature-bowls']);
    }

    public function test_category_creation_requires_bilingual_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        // Missing the Spanish name entirely
        $response = $this->postJson('/api/admin/categories', [
            'name' => ['en' => 'Signature Bowls'],
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors('name.es');
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