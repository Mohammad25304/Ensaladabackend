<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Faq;
use App\Models\MenuItem;
use App\Models\SiteSetting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChatApiTest extends TestCase
{
    use RefreshDatabase;

    private Branch $beirut;

    private Branch $tripoli;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->beirut = $this->makeBranch('Beirut', 'beirut', 'Hamra St', '+961 1 111');
        $this->tripoli = $this->makeBranch('Tripoli', 'tripoli', 'Mina Rd', '+961 6 222');

        $bowls = Category::create([
            'name' => ['en' => 'Signature Bowls', 'es' => 'Bowls'],
            'slug' => 'signature-bowls',
            'description' => ['en' => '', 'es' => ''],
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $vegan = Tag::create(['name' => 'Vegan']);

        $power = $this->makeItem($bowls, 'Vegan Power Bowl', 'Chickpeas, kale and tahini', 390, 15, [$vegan]);
        $chicken = $this->makeItem($bowls, 'Grilled Chicken Bowl', 'Grilled chicken and greens', 480, 42, []);

        // Beirut sells both; Tripoli only sells the chicken bowl — at a different price.
        $power->branches()->attach($this->beirut->id, ['price' => 10.00, 'is_available' => true]);
        $chicken->branches()->attach($this->beirut->id, ['price' => 11.50, 'is_available' => true]);
        $chicken->branches()->attach($this->tripoli->id, ['price' => 12.50, 'is_available' => true]);

        SiteSetting::create(['branch_id' => $this->beirut->id, 'key' => 'hours_weekday', 'value' => 'Mon – Fri: 9am – 8pm']);
    }

    public function test_it_answers_a_menu_question_with_dish_cards(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'vegan options', 'branch' => 'beirut']);

        $response->assertOk()
            ->assertJsonStructure(['text', 'items', 'actions', 'chips', 'is_fallback'])
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name.en', 'Vegan Power Bowl')
            ->assertJsonPath('items.0.price', '10.00')
            ->assertJsonPath('is_fallback', false);
    }

    public function test_prices_follow_the_selected_branch(): void
    {
        $beirut = $this->postJson('/api/chat', ['message' => 'price of the grilled chicken bowl', 'branch' => 'beirut']);
        $tripoli = $this->postJson('/api/chat', ['message' => 'price of the grilled chicken bowl', 'branch' => 'tripoli']);

        $this->assertStringContainsString('$11.50', $beirut->json('text'));
        $this->assertStringContainsString('$12.50', $tripoli->json('text'));
    }

    public function test_items_not_sold_at_a_branch_are_not_offered_there(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'vegan options', 'branch' => 'tripoli']);

        $response->assertOk()->assertJsonCount(0, 'items');
        $this->assertStringContainsString("couldn't find", $response->json('text'));
    }

    public function test_opening_hours_come_from_site_settings(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'what time do you open?', 'branch' => 'beirut']);

        $this->assertStringContainsString('Mon – Fri: 9am – 8pm', $response->json('text'));
    }

    public function test_without_a_branch_it_asks_which_branch(): void
    {
        $response = $this->postJson('/api/chat', ['message' => 'opening hours']);

        $response->assertOk();
        $this->assertSame(['beirut', 'tripoli'], collect($response->json('actions'))->pluck('slug')->all());
    }

    public function test_an_unknown_branch_is_treated_as_no_branch(): void
    {
        $this->postJson('/api/chat', ['message' => 'opening hours', 'branch' => 'atlantis'])
            ->assertOk()
            ->assertJsonPath('actions.0.kind', 'branch');
    }

    public function test_it_escalates_to_the_team_after_repeated_fallbacks(): void
    {
        $first = $this->postJson('/api/chat', ['message' => 'asdfghjk', 'branch' => 'beirut', 'fallback_streak' => 0]);
        $second = $this->postJson('/api/chat', ['message' => 'asdfghjk', 'branch' => 'beirut', 'fallback_streak' => 1]);

        $first->assertJsonPath('is_fallback', true);
        $this->assertStringContainsString('still not sure', $second->json('text'));
    }

    public function test_message_is_required_and_length_limited(): void
    {
        $this->postJson('/api/chat', [])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson('/api/chat', ['message' => str_repeat('a', 301)])
            ->assertUnprocessable()->assertJsonValidationErrors('message');
    }

    public function test_welcome_returns_starter_chips_for_a_branch(): void
    {
        $response = $this->getJson('/api/chat/welcome?branch=beirut');

        $response->assertOk();
        $this->assertStringContainsString('Beirut', $response->json('text'));
        $this->assertContains("What's on the menu?", $response->json('chips'));
        $this->assertContains('Vegan options', $response->json('chips'));
    }

    public function test_welcome_switched_mode_announces_the_new_branch(): void
    {
        $text = $this->getJson('/api/chat/welcome?branch=tripoli&mode=switched')->json('text');

        $this->assertStringContainsString("You're now viewing Tripoli", $text);
    }

    public function test_welcome_rejects_an_invalid_mode(): void
    {
        $this->getJson('/api/chat/welcome?mode=bogus')->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_chat_is_throttled(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/chat', ['message' => 'hi'])->assertOk();
        }

        $this->postJson('/api/chat', ['message' => 'hi'])->assertStatus(429);
    }

    /* ───────────────────────── admin FAQs ───────────────────────── */

    public function test_an_admin_faq_overrides_the_built_in_answer(): void
    {
        Faq::create([
            'question' => 'Delivery',
            'keywords' => ['delivery', 'deliver'],
            'answer' => ['en' => 'We deliver within 5km, 11am to 9pm.'],
        ]);

        $this->postJson('/api/chat', ['message' => 'do you deliver?', 'branch' => 'beirut'])
            ->assertOk()
            ->assertJsonPath('text', 'We deliver within 5km, 11am to 9pm.');
    }

    public function test_a_branch_specific_faq_beats_a_general_one_only_at_that_branch(): void
    {
        Faq::create(['question' => 'Parking', 'keywords' => ['parking'], 'answer' => ['en' => 'Street parking nearby.']]);
        Faq::create([
            'question' => 'Parking (Tripoli)',
            'keywords' => ['parking'],
            'answer' => ['en' => 'Free car park behind the restaurant.'],
            'branch_id' => $this->tripoli->id,
        ]);

        $this->postJson('/api/chat', ['message' => 'is there parking', 'branch' => 'tripoli'])
            ->assertJsonPath('text', 'Free car park behind the restaurant.');
        $this->postJson('/api/chat', ['message' => 'is there parking', 'branch' => 'beirut'])
            ->assertJsonPath('text', 'Street parking nearby.');
    }

    public function test_inactive_faqs_are_ignored(): void
    {
        Faq::create([
            'question' => 'Parking',
            'keywords' => ['parking'],
            'answer' => ['en' => 'Free parking!'],
            'is_active' => false,
        ]);

        $text = $this->postJson('/api/chat', ['message' => 'is there parking', 'branch' => 'beirut'])->json('text');

        $this->assertStringNotContainsString('Free parking!', $text);
    }

    public function test_editing_a_faq_is_reflected_immediately(): void
    {
        $faq = Faq::create(['question' => 'Wifi', 'keywords' => ['wifi'], 'answer' => ['en' => 'Wifi is free.']]);

        // warm the cache
        $this->postJson('/api/chat', ['message' => 'wifi?', 'branch' => 'beirut'])->assertJsonPath('text', 'Wifi is free.');

        $faq->update(['answer' => ['en' => 'Wifi password is on the receipt.']]);

        $this->postJson('/api/chat', ['message' => 'wifi?', 'branch' => 'beirut'])
            ->assertJsonPath('text', 'Wifi password is on the receipt.');
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function makeBranch(string $name, string $slug, string $address, string $phone): Branch
    {
        return Branch::create([
            'name' => ['en' => $name, 'es' => $name],
            'slug' => $slug,
            'address' => $address,
            'phone' => $phone,
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }

    private function makeItem(Category $category, string $name, string $description, int $calories, int $protein, array $tags): MenuItem
    {
        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => ['en' => $name, 'es' => $name],
            'slug' => Str::slug($name),
            'description' => ['en' => $description, 'es' => $description],
            'image' => 'https://placehold.co/400x300',
            'is_featured' => false,
            'calories' => $calories,
            'protein_grams' => $protein,
            'sort_order' => 0,
        ]);

        $item->tags()->attach(collect($tags)->pluck('id')->all());

        return $item;
    }
}
