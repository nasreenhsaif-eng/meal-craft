<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerCraftPlan extends Model
{
    protected $fillable = [
        'customer_profile_id',
        'craft_key',
        'fulfillment_choice',
        'week_duration',
        'plan_weeks',
        'selected_weekdays',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'week_duration' => 'integer',
            'plan_weeks' => 'integer',
            'selected_weekdays' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(CustomerCraftPlanDay::class);
    }
}
