<?php

namespace App\Models;

use Database\Factories\CustomerSubscriptionFreezeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSubscriptionFreeze extends Model
{
    /** @use HasFactory<CustomerSubscriptionFreezeFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_subscription_id',
        'frozen_on',
    ];

    protected function casts(): array
    {
        return [
            'frozen_on' => 'date',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CustomerSubscription::class, 'customer_subscription_id');
    }
}
