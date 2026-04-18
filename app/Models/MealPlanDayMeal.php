<?php

namespace App\Models;

use App\Enums\MealPlanSlotType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanDayMeal extends Model
{
    protected $table = 'meal_plan_day_meal';

    protected $fillable = [
        'meal_plan_id',
        'meal_id',
        'day_number',
        'slot_type',
        'slot_index',
        'is_option_b',
    ];

    protected function casts(): array
    {
        return [
            'slot_type' => MealPlanSlotType::class,
            'is_option_b' => 'boolean',
            'day_number' => 'integer',
            'slot_index' => 'integer',
        ];
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class, 'meal_plan_id');
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
