<?php

namespace Database\Factories;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Meal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meal>
 */
class MealFactory extends Factory
{
    protected $model = Meal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'category' => RecipeCategory::Meal,
            'meal_type' => MealType::Main,
            'total_calories' => 350,
            'total_protein' => 25,
            'total_carbs' => 30,
            'total_fat' => 12,
            'library_sort_order' => fake()->numberBetween(0, 1000),
        ];
    }
}
