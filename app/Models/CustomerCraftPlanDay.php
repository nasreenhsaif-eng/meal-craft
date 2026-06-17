<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCraftPlanDay extends Model
{
    protected $fillable = [
        'customer_craft_plan_id',
        'day_of_week',
        'include_soup',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'include_soup' => 'boolean',
        ];
    }

    public function craftPlan(): BelongsTo
    {
        return $this->belongsTo(CustomerCraftPlan::class, 'customer_craft_plan_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(CustomerCraftPlanDayMeal::class);
    }
}
