<?php

namespace App\Models;

use App\Support\IngredientLibraryCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Ingredient extends Model
{
    use HasFactory;

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
        'description',
        'instructions',
        'finished_weight_grams',
        'image_path',
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
        'diet_tags',
        'common_allergens',
        'is_g6pd_trigger',
        'library_edited_at',
    ];

    protected function casts(): array
    {
        return [
            'source_meal_id' => 'integer',
            'finished_weight_grams' => 'float',
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
            'diet_tags' => 'array',
            'common_allergens' => 'array',
            'is_g6pd_trigger' => 'boolean',
            'library_edited_at' => 'datetime',
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

    /**
     * Child ingredients that make up this prepared base ingredient (per-100 g totals stored on parent).
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'ingredient_component', 'parent_ingredient_id', 'child_ingredient_id')
            ->withPivot(['amount_grams'])
            ->withTimestamps();
    }

    public function isPreparedBaseIngredient(): bool
    {
        return IngredientLibraryCategory::isPrepared($this->usda_food_category);
    }
}
