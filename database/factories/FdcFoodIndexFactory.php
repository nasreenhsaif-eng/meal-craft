<?php

namespace Database\Factories;

use App\Models\FdcFoodIndex;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FdcFoodIndex>
 */
class FdcFoodIndexFactory extends Factory
{
    protected $model = FdcFoodIndex::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fdc_id' => $this->faker->unique()->numberBetween(100_000, 999_999_999),
            'data_type' => 'Foundation',
            'description' => $this->faker->words(4, true).', raw',
            'ndb_number' => (string) $this->faker->numberBetween(1000, 99999),
            'food_category' => 'Fruits and Fruit Juices',
            'publication_date' => $this->faker->date('Y-m-d'),
        ];
    }
}
