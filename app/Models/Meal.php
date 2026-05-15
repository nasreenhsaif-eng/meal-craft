<?php

namespace App\Models;

use App\Enums\CyclePhase;
use App\Enums\DietType;
use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Services\RecipeNutritionCalculator;
use App\Support\MealImagePath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meal extends Model
{
    protected $fillable = [
        'name',
        'category',
        'meal_type',
        'finished_weight_grams',
        'is_bulk',
        'servings_count',
        'target_calories',
        'target_protein',
        'target_carbs',
        'target_fat',
        'description',
        'highlight',
        'image_path',
        'health_score',
        'total_calories',
        'total_protein',
        'total_carbs',
        'total_fat',
        'total_b6',
        'total_folate',
        'total_b12',
        'total_iron',
        'total_magnesium',
        'total_fiber',
        'total_sugar',
        'total_calcium',
        'total_potassium',
        'total_sodium',
        'total_zinc',
        'total_vitamin_c',
        'total_vitamin_a',
        'total_vitamin_e',
        'total_vitamin_d',
        'total_vitamin_k',
        'cycle_phase_tags',
        'cycle_phase_tags_manual',
        'cycle_phase_compatibility_tooltips',
        'diet_tags',
        'diet_type',
        'meal_plan_tag',
        'meal_plan_tags',
        'cycle_phase',
        'cycle_phases',
        'macro_focus',
        'safety_alert_tags',
        'sickle_cell_program_highlight',
        'nutrition_aggregates_synced',
    ];

    protected function casts(): array
    {
        return [
            'category' => RecipeCategory::class,
            'meal_type' => MealType::class,
            'finished_weight_grams' => 'float',
            'is_bulk' => 'boolean',
            'servings_count' => 'float',
            'target_calories' => 'float',
            'target_protein' => 'float',
            'target_carbs' => 'float',
            'target_fat' => 'float',
            'health_score' => 'float',
            'total_calories' => 'float',
            'total_protein' => 'float',
            'total_carbs' => 'float',
            'total_fat' => 'float',
            'total_b6' => 'float',
            'total_folate' => 'float',
            'total_b12' => 'float',
            'total_iron' => 'float',
            'total_magnesium' => 'float',
            'total_fiber' => 'float',
            'total_sugar' => 'float',
            'total_calcium' => 'float',
            'total_potassium' => 'float',
            'total_sodium' => 'float',
            'total_zinc' => 'float',
            'total_vitamin_c' => 'float',
            'total_vitamin_a' => 'float',
            'total_vitamin_e' => 'float',
            'total_vitamin_d' => 'float',
            'total_vitamin_k' => 'float',
            'cycle_phase_tags' => 'array',
            'cycle_phase_tags_manual' => 'boolean',
            'cycle_phase_compatibility_tooltips' => 'array',
            'diet_tags' => 'array',
            'diet_type' => DietType::class,
            'meal_plan_tags' => 'array',
            'cycle_phases' => 'array',
            'cycle_phase' => CyclePhase::class,
            'safety_alert_tags' => 'array',
            'sickle_cell_program_highlight' => 'boolean',
            'nutrition_aggregates_synced' => 'boolean',
        ];
    }

    /**
     * Prepared base ingredients belong in the Ingredient Library only, not the Meal Library.
     *
     * @param  Builder<Meal>  $query
     * @return Builder<Meal>
     */
    public function scopeVisibleInMealLibrary(Builder $query): Builder
    {
        return $query
            ->where('meal_type', '!=', MealType::BaseRecipe->value)
            ->where(function (Builder $inner): void {
                $inner->whereNull('category')
                    ->orWhere('category', '!=', RecipeCategory::BaseRecipe);
            });
    }

    /**
     * @param  Builder<Meal>  $query
     * @return Builder<Meal>
     */
    public function scopeMenstrual(Builder $query): Builder
    {
        return $query->where('cycle_phase', CyclePhase::Menstrual);
    }

    /**
     * @param  Builder<Meal>  $query
     * @return Builder<Meal>
     */
    public function scopeFollicular(Builder $query): Builder
    {
        return $query->where('cycle_phase', CyclePhase::Follicular);
    }

    /**
     * @param  Builder<Meal>  $query
     * @return Builder<Meal>
     */
    public function scopeOvulatory(Builder $query): Builder
    {
        return $query->where('cycle_phase', CyclePhase::Ovulatory);
    }

    /**
     * @param  Builder<Meal>  $query
     * @return Builder<Meal>
     */
    public function scopeLuteal(Builder $query): Builder
    {
        return $query->where('cycle_phase', CyclePhase::Luteal);
    }

    public function imageUrl(): ?string
    {
        $url = MealImagePath::resolveUrl($this->image_path);

        return $url === '' ? null : $url;
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class)
            ->withPivot(['amount_grams', 'amount', 'unit'])
            ->withTimestamps();
    }

    public function derivedLibraryIngredient(): HasOne
    {
        return $this->hasOne(Ingredient::class, 'source_meal_id');
    }

    public function mealPlans(): BelongsToMany
    {
        return $this->belongsToMany(MealPlan::class)
            ->withPivot(['day_of_week', 'meal_type'])
            ->withTimestamps();
    }

    /**
     * @param  array<string, float>  $nutrition
     * @return array<string, float>
     */
    public static function nutritionSummaryToPersistedAttributes(array $nutrition): array
    {
        return [
            'total_calories' => round((float) ($nutrition['calories'] ?? 0), 2),
            'total_protein' => round((float) ($nutrition['protein'] ?? 0), 2),
            'total_carbs' => round((float) ($nutrition['carbs'] ?? 0), 2),
            'total_fat' => round((float) ($nutrition['fat'] ?? 0), 2),
            'total_b6' => round((float) ($nutrition['b6'] ?? 0), 4),
            'total_folate' => round((float) ($nutrition['b9_folate'] ?? 0), 4),
            'total_b12' => round((float) ($nutrition['b12'] ?? 0), 4),
            'total_iron' => round((float) ($nutrition['iron'] ?? 0), 4),
            'total_magnesium' => round((float) ($nutrition['magnesium'] ?? 0), 4),
            'total_fiber' => round((float) ($nutrition['fiber'] ?? 0), 4),
            'total_sugar' => round((float) ($nutrition['sugar'] ?? 0), 4),
            'total_calcium' => round((float) ($nutrition['calcium'] ?? 0), 4),
            'total_potassium' => round((float) ($nutrition['potassium'] ?? 0), 4),
            'total_sodium' => round((float) ($nutrition['sodium'] ?? 0), 4),
            'total_zinc' => round((float) ($nutrition['zinc'] ?? 0), 4),
            'total_vitamin_c' => round((float) ($nutrition['vitamin_c'] ?? 0), 4),
            'total_vitamin_a' => round((float) ($nutrition['vitamin_a'] ?? 0), 4),
            'total_vitamin_e' => round((float) ($nutrition['vitamin_e'] ?? 0), 4),
            'total_vitamin_d' => round((float) ($nutrition['vitamin_d'] ?? 0), 4),
            'total_vitamin_k' => round((float) ($nutrition['vitamin_k'] ?? 0), 4),
        ];
    }

    /**
     * @return array<string, float>
     */
    public function persistedNutritionAsCalculatorShape(): array
    {
        return [
            'calories' => (float) $this->total_calories,
            'protein' => (float) $this->total_protein,
            'carbs' => (float) $this->total_carbs,
            'fat' => (float) $this->total_fat,
            'b6' => (float) $this->total_b6,
            'b9_folate' => (float) $this->total_folate,
            'b12' => (float) $this->total_b12,
            'iron' => (float) $this->total_iron,
            'magnesium' => (float) $this->total_magnesium,
            'fiber' => (float) $this->total_fiber,
            'sugar' => (float) $this->total_sugar,
            'calcium' => (float) $this->total_calcium,
            'potassium' => (float) $this->total_potassium,
            'sodium' => (float) $this->total_sodium,
            'zinc' => (float) $this->total_zinc,
            'vitamin_c' => (float) $this->total_vitamin_c,
            'vitamin_a' => (float) $this->total_vitamin_a,
            'vitamin_e' => (float) $this->total_vitamin_e,
            'vitamin_d' => (float) $this->total_vitamin_d,
            'vitamin_k' => (float) $this->total_vitamin_k,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function nutritionForDisplay(): array
    {
        $stored = $this->persistedNutritionAsCalculatorShape();

        $macrosEmpty = (float) $this->total_calories <= 0
            && (float) $this->total_protein <= 0
            && (float) $this->total_carbs <= 0
            && (float) $this->total_fat <= 0;

        if ($macrosEmpty && $this->ingredients()->exists()) {
            return RecipeNutritionCalculator::fromMeal(
                $this->relationLoaded('ingredients') ? $this : $this->load('ingredients')
            );
        }

        return $stored;
    }

    /**
     * @return array<string, float>
     */
    public function calculatedNutrition(): array
    {
        return RecipeNutritionCalculator::fromMeal($this);
    }

    /**
     * Whether this meal is classified as a base recipe in the library (triggers derived ingredient sync).
     */
    public function isBaseRecipeCategory(): bool
    {
        return $this->category === RecipeCategory::BaseRecipe;
    }
}
