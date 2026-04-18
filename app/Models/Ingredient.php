<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    /**
     * @return array<string, bool>
     */
    public function getHighlightsAttribute(): array
    {
        $micros = is_array($this->micronutrients) ? $this->micronutrients : [];
        $zinc = (float) ($micros['zinc'] ?? 0);

        return [
            'folate' => (float) $this->b9_folate > 100.0,
            'b12' => (float) $this->b12 > 2.0,
            'magnesium' => (float) $this->magnesium > 100.0,
            'iron' => (float) $this->iron > 5.0,
            'zinc' => $zinc > 3.0,
        ];
    }

    protected $fillable = [
        'source_meal_id',
        'name',
        'usda_food_category',
        'fdc_id',
        'calories',
        'protein',
        'carbs',
        'fat',
        'b6',
        'b9_folate',
        'b12',
        'iron',
        'magnesium',
        'density',
        'is_verified',
        'micronutrients',
    ];

    protected function casts(): array
    {
        return [
            'source_meal_id' => 'integer',
            'fdc_id' => 'integer',
            'calories' => 'float',
            'protein' => 'float',
            'carbs' => 'float',
            'fat' => 'float',
            'b6' => 'float',
            'b9_folate' => 'float',
            'b12' => 'float',
            'iron' => 'float',
            'magnesium' => 'float',
            'density' => 'float',
            'is_verified' => 'boolean',
            'micronutrients' => 'array',
        ];
    }

    /**
     * @return array{b6: float, b9_folate: float, b12: float, iron: float, magnesium: float}
     */
    public function resolvedSickleMicrosPer100g(): array
    {
        $m = is_array($this->micronutrients) ? $this->micronutrients : [];

        $pick = function (string $column, string $jsonKey) use ($m): float {
            $fromCol = (float) ($this->getAttribute($column) ?? 0);

            if ($fromCol != 0.0) {
                return $fromCol;
            }

            return (float) ($m[$jsonKey] ?? 0);
        };

        return [
            'b6' => $pick('b6', 'vitamin_b6'),
            'b9_folate' => $pick('b9_folate', 'vitamin_b9'),
            'b12' => $pick('b12', 'vitamin_b12'),
            'iron' => $pick('iron', 'iron'),
            'magnesium' => $pick('magnesium', 'magnesium'),
        ];
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class)
            ->withPivot(['amount_grams', 'amount', 'unit'])
            ->withTimestamps();
    }

    public function sourceMeal(): BelongsTo
    {
        return $this->belongsTo(Meal::class, 'source_meal_id');
    }

    public function isDerivedFromRecipe(): bool
    {
        return $this->source_meal_id !== null;
    }
}
