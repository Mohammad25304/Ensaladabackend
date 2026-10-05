<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MenuItemApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->branch = Branch::create([
            'name' => ['en' => 'Beirut', 'es' => 'Beirut'],
            'slug' => 'beirut',
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    /** Create a menu item and offer it at the test branch. */
    private function offer(array $attributes = [], float $price = 9.50, bool $available = true): MenuItem
    {
        $item = MenuItem::factory()->create($attributes);
        $item->branches()->attach($this->branch->id, ['price' => $price, 'is_available' => $available]);

        return $item;
    }

    public function test_branch_is_required(): void
    {
        $this->getJson('/api/menu-items')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('branch');
    }

    public function test_unknown_branch_returns_not_found(): void
    {
        $this->getJson('/api/menu-items?branch=atlantis')->assertNotFound();
    }

    public function test_public_only_sees_items_available_at_the_branch(): void
    {
        $this->offer(available: true);
        $this->offer(available: false);   // switched off at this branch
        MenuItem::factory()->create();    // not offered here at all

        $this->getJson('/api/menu-items?branch=beirut')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_price_comes_from_the_branch(): void
    {
        $this->offer(price: 12.50);

        $this->getJson('/api/menu-items?branch=beirut')
            ->assertOk()
            ->assertJsonPath('0.price', '12.50');
    }

    public function test_public_can_filter_by_category(): void
    {
        $bowls = Category::factory()->create(['slug' => 'bowls']);
        $drinks = Category::factory()->create(['slug' => 'drinks']);

        $this->offer(['category_id' => $bowls->id]);
        $this->offer(['category_id' => $drinks->id]);

        $this->getJson('/api/menu-items?branch=beirut&category=bowls')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_public_can_filter_featured_items(): void
    {
        $this->offer(['is_featured' => true]);
        $this->offer(['is_featured' => false]);

        $this->getJson('/api/menu-items?branch=beirut&featured=1')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_public_can_filter_by_tag(): void
    {
        $vegan = Tag::create(['name' => 'Vegan']);

        $this->offer()->tags()->attach($vegan->id);
        $this->offer();

        $this->getJson('/api/menu-items?branch=beirut&tag=Vegan')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_guest_cannot_create_menu_item(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/admin/menu-items', [
            'category_id' => $category->id,
            'name' => ['en' => 'Test Item', 'es' => 'Artículo de Prueba'],
            'description' => ['en' => 'A test item', 'es' => 'Un artículo de prueba'],
        ])->assertUnauthorized();
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
            'image' => UploadedFile::fake()->create('caesar.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('name.en', 'Caesar Salad');
        $this->assertDatabaseHas('menu_items', ['slug' => 'caesar-salad']);
    }

    public function test_menu_item_requires_valid_category(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/menu-items', [
            'category_id' => 999,
            'name' => ['en' => 'Test Item', 'es' => 'Artículo de Prueba'],
            'description' => ['en' => 'Test', 'es' => 'Prueba'],
        ])->assertUnprocessable()->assertJsonValidationErrors('category_id');
    }

    public function test_admin_can_set_per_branch_prices(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = MenuItem::factory()->create();
        Sanctum::actingAs($admin);

        $this->putJson("/api/admin/menu-items/{$item->id}/branches", [
            'branches' => [['branch_id' => $this->branch->id, 'price' => 11.00, 'is_available' => true]],
        ])->assertOk();

        $this->assertDatabaseHas('branch_menu_item', [
            'menu_item_id' => $item->id,
            'branch_id' => $this->branch->id,
            'price' => 11.00,
        ]);
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
