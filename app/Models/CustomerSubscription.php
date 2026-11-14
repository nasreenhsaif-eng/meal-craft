<?php

namespace App\Models;

use App\Enums\CustomerSubscriptionStatus;
use Database\Factories\CustomerSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSubscription extends Model
{
    /** @use HasFactory<CustomerSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_profile_id',
        'customer_craft_plan_id',
        'status',
        'paid_at',
        'starts_on',
        'expires_on',
        'delivery_days_count',
        'fulfillment_choice',
        'craft_key',
        'meal_plan_name',
        'amount_bhd',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerSubscriptionStatus::class,
            'paid_at' => 'datetime',
            'starts_on' => 'date',
            'expires_on' => 'date',
            'delivery_days_count' => 'integer',
            'amount_bhd' => 'decimal:3',
        ];
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function craftPlan(): BelongsTo
    {
        return $this->belongsTo(CustomerCraftPlan::class, 'customer_craft_plan_id');
    }

    public function freezes(): HasMany
    {
        return $this->hasMany(CustomerSubscriptionFreeze::class);
    }
}
