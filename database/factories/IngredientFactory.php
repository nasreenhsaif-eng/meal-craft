<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'usda_food_category' => fake()->randomElement(['Proteins', 'Fats', 'Grains', 'Vegetables']),
            'fdc_id' => fake()->optional()->numberBetween(1000, 9999),
            'calories' => fake()->randomFloat(2, 0, 500),
            'protein' => fake()->randomFloat(2, 0, 60),
            'carbs' => fake()->randomFloat(2, 0, 120),
            'fat' => fake()->randomFloat(2, 0, 60),
            'b6' => fake()->randomFloat(2, 0, 10),
            'b9_folate' => fake()->randomFloat(2, 0, 500),
            'b12' => fake()->randomFloat(2, 0, 20),
            'iron' => fake()->randomFloat(2, 0, 20),
            'magnesium' => fake()->randomFloat(2, 0, 300),
            'density' => fake()->randomFloat(2, 0.1, 3),
            'is_verified' => false,
            'micronutrients' => [],
        ];
    }
}
