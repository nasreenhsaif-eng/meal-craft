<?php

namespace App\Models;

use App\Enums\MealCyclePhaseTag;
use App\Enums\MealPlanLibraryCategory;
use App\Enums\MealPlanSchemaType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class MealPlan extends Model
{
    protected $fillable = [
        'name',
        'goal',
        'schema_type',
        'plan_category',
        'cycle_phase',
        'target_total_calories',
        'target_total_protein_g',
        'target_total_carbs_g',
        'target_total_fat_g',
    ];

    protected function casts(): array
    {
        return [
            'schema_type' => MealPlanSchemaType::class,
            'plan_category' => MealPlanLibraryCategory::class,
            'cycle_phase' => MealCyclePhaseTag::class,
            'target_total_calories' => 'float',
            'target_total_protein_g' => 'float',
            'target_total_carbs_g' => 'float',
            'target_total_fat_g' => 'float',
        ];
    }

    public function usesStructuredDaySlots(): bool
    {
        return $this->schema_type === MealPlanSchemaType::FourWeek
            || $this->schema_type === MealPlanSchemaType::WeeklyStructured;
    }

    public function structuredPlanningDayCount(): int
    {
        return match ($this->schema_type) {
            MealPlanSchemaType::FourWeek => 28,
            MealPlanSchemaType::WeeklyStructured => 7,
            default => 0,
        };
    }

    public function meals(): BelongsToMany
    {
        return $this->belongsToMany(Meal::class)
            ->withPivot(['day_of_week', 'meal_type'])
            ->withTimestamps();
    }

    public function dayMeals(): HasMany
    {
        return $this->hasMany(MealPlanDayMeal::class);
    }
}
