<?php

namespace App\Models;

use Database\Factories\PartnerKitchenPriceCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerKitchenPriceCard extends Model
{
    /** @use HasFactory<PartnerKitchenPriceCardFactory> */
    use HasFactory;

    protected $fillable = [
        'scope',
        'calorie_row',
        'billed_days',
        'amount_bhd',
    ];

    protected function casts(): array
    {
        return [
            'calorie_row' => 'integer',
            'billed_days' => 'integer',
            'amount_bhd' => 'float',
        ];
    }
}
