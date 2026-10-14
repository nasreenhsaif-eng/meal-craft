<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MealCalorieTier extends Model
{
    protected $fillable = [
        'meal_id',
        'calorie_tier',
        'designed_calories',
        'total_calories',
        'total_protein',
        'total_carbs',
        'total_fat',
        'nutrition',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calorie_tier' => 'integer',
            'designed_calories' => 'float',
            'total_calories' => 'float',
            'total_protein' => 'float',
            'total_carbs' => 'float',
            'total_fat' => 'float',
            'nutrition' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Meal, $this>
     */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }

    /**
     * @return BelongsToMany<Ingredient, $this>
     */
    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'ingredient_meal_tier')
            ->withPivot(['amount_grams'])
            ->withTimestamps();
    }
}
