<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => [
                'en' => ucwords($name),
                'es' => ucwords($name),
            ],
            'slug' => Str::slug($name),
            'description' => [
                'en' => fake()->sentence(12),
                'es' => fake()->sentence(12),
            ],
            'price' => fake()->randomFloat(2, 5, 25),
            'image' => 'https://placehold.co/400x300',
            'image_public_id' => null,
            'is_featured' => false,
            'is_available' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}