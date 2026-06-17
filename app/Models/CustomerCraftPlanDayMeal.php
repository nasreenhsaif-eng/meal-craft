<?php

namespace App\Models;

use App\Enums\CustomerCraftMealSlot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerCraftPlanDayMeal extends Model
{
    protected $fillable = [
        'customer_craft_plan_day_id',
        'meal_id',
        'slot',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'meal_id' => 'integer',
            'position' => 'integer',
            'slot' => CustomerCraftMealSlot::class,
        ];
    }

    public function planDay(): BelongsTo
    {
        return $this->belongsTo(CustomerCraftPlanDay::class, 'customer_craft_plan_day_id');
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(Meal::class);
    }
}
