<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => [
                'en' => ucwords($name),
                'es' => ucwords($name), // placeholder — real Spanish content added via admin
            ],
            'slug' => Str::slug($name),
            'description' => [
                'en' => fake()->sentence(),
                'es' => fake()->sentence(),
            ],
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }
}